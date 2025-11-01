from flask import Flask, render_template, request, redirect, url_for
import os
import pymysql
from datetime import datetime
import re  # Para validações regex

app = Flask(__name__)

# Configurações DB de env vars (do docker-compose)
DB_HOST = os.environ.get('DB_HOST', 'db')
DB_NAME = os.environ.get('DB_NAME', 'firenet')
DB_USER = os.environ.get('DB_USER', 'root')
DB_PASSWORD = os.environ.get('DB_PASSWORD', 'example')

def get_db_connection():
    return pymysql.connect(
        host=DB_HOST,
        user=DB_USER,
        password=DB_PASSWORD,
        database=DB_NAME,
        charset='utf8mb4'
    )

# Função para sanitizar inputs (similar a PHP)
def sanitize(data):
    return re.sub(r'[^\w\s-]', '', data.strip()) if data else None

# Validação CPF (exata do PHP)
def validar_cpf(cpf):
    cpf = re.sub(r'\D', '', cpf)
    if len(cpf) != 11 or re.match(r'^(\d)\1+$', cpf):
        return False
    for t in [9, 10]:
        d = sum(int(cpf[c]) * ((t + 1) - c) for c in range(t))
        d = ((10 * d) % 11) % 10
        if int(cpf[t]) != d:
            return False
    return True

# Check e create tabelas ao iniciar
def init_db():
    conn = get_db_connection()
    cursor = conn.cursor()

    # Verifica se tabelas existem
    cursor.execute("SHOW TABLES")
    existing_tables = [table[0] for table in cursor.fetchall()]

    if 'cadastros' not in existing_tables:
        cursor.execute("""
            CREATE TABLE cadastros (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nome VARCHAR(255) NOT NULL,
                cpf VARCHAR(14) NOT NULL UNIQUE,
                rg VARCHAR(12) NOT NULL,
                data_nascimento VARCHAR(10) NOT NULL,
                telefone VARCHAR(15) NOT NULL,
                whatsapp ENUM('Sim', 'Não') NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                cep VARCHAR(9) NOT NULL,
                rua VARCHAR(255) NOT NULL,
                numero VARCHAR(50) NOT NULL,
                complemento VARCHAR(255),
                ponto_referencia VARCHAR(255) NOT NULL,
                bairro VARCHAR(255) NOT NULL,
                plano ENUM('500 MEGA', '600 MEGA', '700 MEGA', '800 MEGA') NOT NULL,
                vencimento ENUM('Dia 5', 'Dia 10', 'Dia 15') NOT NULL,
                nome_rede VARCHAR(255) NOT NULL,
                senha_rede VARCHAR(10) NOT NULL,
                lgpd ENUM('Sim') NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        """)

    if 'password_resets' not in existing_tables:
        cursor.execute("""
            CREATE TABLE password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                token VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX(email)
            )
        """)

    if 'usuarios' not in existing_tables:
        cursor.execute("""
            CREATE TABLE usuarios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                permissoes ENUM('visualizar', 'excluir') DEFAULT 'visualizar',
                email VARCHAR(255) UNIQUE NOT NULL,
                2fa_enabled TINYINT DEFAULT 0,
                2fa_type VARCHAR(10),
                2fa_secret VARCHAR(255)
            )
        """)

    if 'relatorios' not in existing_tables:
        cursor.execute("""
            CREATE TABLE relatorios (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT,
                event VARCHAR(255) NOT NULL,
                timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES usuarios(id) ON DELETE CASCADE
            )
        """)

    conn.commit()
    cursor.close()
    conn.close()

# Inicia DB check
init_db()

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/check_cpf', methods=['POST'])
def check_cpf():
    """
    Endpoint chamado pelo JavaScript do cadastro.html para
    verificar se o CPF já existe (validação em tempo real).
    """
    from flask import jsonify
    data = request.get_json(silent=True) or {}
    cpf_raw = data.get('cpf', '') or ''
    cpf_num = re.sub(r'\D', '', cpf_raw)

    exists = False
    if cpf_num:
        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            # Compara removendo pontuação do que está salvo
            cursor.execute(
                "SELECT COUNT(*) FROM cadastros "
                "WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = %s",
                (cpf_num,)
            )
            exists = cursor.fetchone()[0] > 0
            cursor.close()
            conn.close()
        except Exception as e:
            # Em caso de falha, não travar o front; responder exists=False
            print("Erro ao verificar CPF:", e)
            exists = False

    return jsonify({'exists': exists})

@app.route('/cadastro', methods=['GET', 'POST'])
def cadastro():
    current_year = datetime.now().year
    if request.method == 'POST':
        try:
            # Captura e sanitiza
            nome = request.form.get('nome')
            if nome:
                nome = ' '.join(word.capitalize() for word in nome.lower().split())
            cpf = sanitize(request.form.get('cpf'))
            rg = sanitize(request.form.get('rg'))
            data_nascimento = request.form.get('data_nascimento')
            telefone = sanitize(request.form.get('telefone'))
            whatsapp = request.form.get('whatsapp')
            email = request.form.get('email')
            cep = sanitize(request.form.get('cep'))

            # Nota: agora o campo visual de endereço é apenas display,
            # e enviamos rua (logradouro) e bairro via inputs ocultos.
            rua = sanitize(request.form.get('rua') or request.form.get('rua_hidden'))
            numero = sanitize(request.form.get('numero'))
            complemento = sanitize(request.form.get('complemento'))
            ponto_referencia = sanitize(request.form.get('ponto_referencia'))
            bairro = sanitize(request.form.get('bairro'))  # vem oculto via JS
            plano = request.form.get('plano')
            vencimento = request.form.get('vencimento')
            nome_rede = sanitize(request.form.get('nome_rede'))
            senha_rede = sanitize(request.form.get('senha_rede'))
            lgpd = request.form.get('lgpd')

            # Validações (replicadas do PHP)
            required_fields = [
                nome, cpf, rg, data_nascimento, telefone, whatsapp, email, cep,
                rua, numero, ponto_referencia, bairro, plano, vencimento,
                nome_rede, senha_rede, lgpd
            ]
            if None in required_fields or '' in required_fields:
                raise ValueError('Todos os campos obrigatórios devem ser preenchidos.')

            if not validar_cpf(cpf):
                raise ValueError('CPF inválido.')

            if not re.match(r'^[\w\.-]+@[\w\.-]+\.\w+$', email):
                raise ValueError('E-mail inválido.')

            if whatsapp not in ['Sim', 'Não']:
                raise ValueError('Valor inválido para WhatsApp.')

            if vencimento not in ['Dia 5', 'Dia 10', 'Dia 15']:
                raise ValueError('Dia de vencimento inválido.')

            if plano not in ['500 MEGA', '600 MEGA', '700 MEGA', '800 MEGA']:
                raise ValueError('Plano inválido.')

            if len(senha_rede) < 8 or len(senha_rede) > 10:
                raise ValueError('A senha da rede deve ter entre 8 e 10 caracteres.')

            if lgpd != 'Sim':
                raise ValueError('Você deve concordar com a LGPD.')

            # Converte data
            date_obj = datetime.strptime(data_nascimento, '%Y-%m-%d')
            data_nascimento = date_obj.strftime('%d/%m/%Y')

            # ===== Verifica se CPF já existe antes de inserir =====
            cpf_num = re.sub(r'\D', '', cpf or '')
            conn = get_db_connection()
            cursor = conn.cursor()
            cursor.execute(
                "SELECT COUNT(*) FROM cadastros "
                "WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = %s",
                (cpf_num,)
            )
            if cursor.fetchone()[0] > 0:
                cursor.close()
                conn.close()
                raise ValueError('CPF já cadastrado, favor entrar em contato via WhatsApp.')

            # Se não existe, faz o insert
            cursor.execute("""
                INSERT INTO cadastros (
                    nome, cpf, rg, data_nascimento, telefone, whatsapp,
                    email, cep, rua, numero, complemento, ponto_referencia,
                    bairro, plano, vencimento, nome_rede, senha_rede, lgpd
                )
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                nome, cpf, rg, data_nascimento, telefone, whatsapp, email, cep,
                rua, numero, complemento, ponto_referencia, bairro, plano,
                vencimento, nome_rede, senha_rede, lgpd
            ))
            conn.commit()
            cursor.close()
            conn.close()

            return redirect(url_for('sucesso'))

        except ValueError as e:
            # Mensagens de validação amigáveis
            return render_template('cadastro.html', error=str(e), form_data=request.form, current_year=current_year)

        except pymysql.err.IntegrityError as e:
            # Caso extremo: se UNIQUE estourar (condição de corrida), tratar amigável
            msg = str(e.args[1] if len(e.args) > 1 else e)
            if 'cpf' in msg.lower():
                friendly = 'CPF já cadastrado, favor entrar em contato via WhatsApp.'
            elif 'email' in msg.lower():
                friendly = 'E-mail já cadastrado. Use outro e-mail ou recupere o acesso.'
            else:
                friendly = 'Não foi possível concluir o cadastro. Tente novamente.'
            return render_template('cadastro.html', error=friendly, form_data=request.form, current_year=current_year)

        except Exception as e:
            # Loga e retorna mensagem genérica
            print("Erro interno no /cadastro:", e)
            return render_template('cadastro.html', error='Erro interno. Tente novamente.', form_data=request.form, current_year=current_year)

    return render_template('cadastro.html', current_year=current_year)

@app.route('/sucesso')
def sucesso():
    return render_template('sucesso.html')  # Crie sucesso.html similar ao PHP

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=False)
