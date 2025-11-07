# app.py (modificado)
from flask import Flask, render_template, request, redirect, url_for, jsonify, session, flash
from flask_mail import Mail, Message
import requests
import os
from datetime import datetime, timedelta  # Adicionado timedelta para sessões permanentes
import re
from sql import get_db_connection, wait_for_db, init_db
from werkzeug.security import check_password_hash  # Novo para login
from werkzeug.security import generate_password_hash
import pymysql  # Adicionado para usar pymysql.cursors.DictCursor
import asyncio  # Para rodar async
from telegram import Bot  # Da biblioteca python-telegram-bot
from telegram.error import TelegramError  # Para tratar erros
import threading  # Adicionado para tarefas em background
from datetime import datetime
import uuid

app = Flask(__name__)
app.secret_key = os.environ.get('SECRET_KEY', 'default_secret_key')  # Novo para sessions
app.permanent_session_lifetime = timedelta(days=1)  # Define o tempo de vida da sessão permanente (ajuste conforme necessário)
app.config['MAIL_SERVER'] = os.environ.get('MAIL_SERVER')
app.config['MAIL_PORT'] = int(os.environ.get('MAIL_PORT', 587))
app.config['MAIL_USE_TLS'] = os.environ.get('MAIL_USE_TLS', 'True') == 'True'
app.config['MAIL_USE_SSL'] = os.environ.get('MAIL_USE_SSL', 'False') == 'True'
app.config['MAIL_USERNAME'] = os.environ.get('MAIL_USERNAME')
app.config['MAIL_PASSWORD'] = os.environ.get('MAIL_PASSWORD')
app.config['MAIL_DEFAULT_SENDER'] = os.environ.get('MAIL_DEFAULT_SENDER')

mail = Mail(app)

app.config['REPLY_TO_EMAIL'] = os.environ.get('REPLY_TO_EMAIL')
app.config['RECAPTCHA_SITE_KEY'] = os.environ.get('RECAPTCHA_SITE_KEY')
app.config['RECAPTCHA_SECRET_KEY'] = os.environ.get('RECAPTCHA_SECRET_KEY')

# Configurações não-DB
GOOGLE_MAPS_API_KEY = os.environ.get('GOOGLE_MAPS_API_KEY', '')

# Fallback para base_url via env var (útil para testes ou crons sem request context)
APP_BASE_URL = os.environ.get('APP_BASE_URL', 'http://localhost:5000/')

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


def _next_identificador(cursor):
    """
    Gera identificador NNNN de forma segura (contínuo, com reset após 9999).
    Usa LOCK TABLES/UNLOCK para evitar corrida entre múltiplos inserts concorrentes.
    """
    # trava a tabela só durante o cálculo e o insert do identificador
    cursor.execute("LOCK TABLES relatorios WRITE")
    try:
        # pega o maior sequencial e soma 1
        cursor.execute("""
            SELECT MAX(CAST(identificador AS UNSIGNED)) AS max_seq
            FROM relatorios
        """)
        row = cursor.fetchone()
        prox = (row[0] or 0) + 1
        if prox > 9999:
            prox = 1
        return f"{prox:04d}"
    finally:
        cursor.execute("UNLOCK TABLES")


@app.route('/')
def index():
    print(f"Sessão na home: {session.get('user_id')}")  # Log temporário para debug
    current_year = datetime.now().year
    return render_template('index.html', 
                           current_year=current_year, 
                           google_maps_api_key=GOOGLE_MAPS_API_KEY)

@app.route('/contato', methods=['GET'])
def contato():
    return render_template('contato.html', recaptcha_site_key=app.config['RECAPTCHA_SITE_KEY'])

@app.route('/vantagens/ipv6-ipv4-fixo')
def vantagens_ipv6():
    return render_template('vantagens/ipv6_ipv4_fixo.html')

@app.route('/vantagens/baixa-latencia')
def vantagens_latencia():
    return render_template('vantagens/baixa_latencia.html')

@app.route('/vantagens/dns-proprio')
def vantagens_dns():
    return render_template('vantagens/dns_proprio.html')

@app.route('/vantagens/conexao-estavel')
def vantagens_estavel():
    return render_template('vantagens/conexao_estavel.html')

@app.route('/vantagens/upload-proporcional')
def vantagens_upload():
    return render_template('vantagens/upload_proporcional.html')

@app.route('/vantagens/instalacao-rapida')
def vantagens_instalacao():
    return render_template('vantagens/instalacao_rapida.html')

@app.route('/vantagens/whatsapp-rapido')
def vantagens_whatsapp():
    return render_template('vantagens/whatsapp_rapido.html')

@app.route('/vantagens/infraestrutura-premium')
def vantagens_infra():
    return render_template('vantagens/infraestrutura_premium.html')

@app.route('/check_cpf', methods=['POST'])
def check_cpf():
    """
    Endpoint chamado pelo JavaScript do cadastro.html para
    verificar se o CPF já existe (validação em tempo real).
    """
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
            # Captura o base_url dinamicamente do request
            base_url = request.url_root or APP_BASE_URL  # Fallback se não houver request

            # Captura e sanitiza (mantendo sanitize nos campos apropriados, mas removendo da senha_rede)
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
            nome_rede = sanitize(request.form.get('nome_rede'))  # Mantido, mas opcional remover se quiser permitir especiais no SSID
            senha_rede = request.form.get('senha_rede').strip()  # Sem sanitize! Apenas strip para remover espaços extras
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

            # Novos valores padrão
            data_instalacao_atual = datetime.now().strftime('%d/%m/%Y')  # Data atual no formato dd/mm/yyyy
            turno_instalacao_padrao = 'Horário Comercial'
            status_instalacao_padrao = 'N/D'  # Ou None se não alterar o enum

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
                    bairro, plano, vencimento, nome_rede, senha_rede, lgpd,
                    data_instalacao, turno_instalacao, status_instalacao
                )
                VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
            """, (
                nome, cpf, rg, data_nascimento, telefone, whatsapp, email, cep,
                rua, numero, complemento, ponto_referencia, bairro, plano,
                vencimento, nome_rede, senha_rede, lgpd,
                data_instalacao_atual, turno_instalacao_padrao, status_instalacao_padrao
            ))
            conn.commit()
            cursor.close()
            conn.close()

            # Preparar dados para notificações
            cadastro_data = {
                'nome': nome,
                'cpf': cpf,
                'rg': rg,
                'data_nascimento': data_nascimento,
                'telefone': telefone,
                'whatsapp': whatsapp,
                'email': email,
                'cep': cep,
                'rua': rua,
                'numero': numero,
                'complemento': complemento,
                'ponto_referencia': ponto_referencia,
                'bairro': bairro,
                'plano': plano,
                'vencimento': vencimento,
                'nome_rede': nome_rede,
                'senha_rede': senha_rede,
                'lgpd': lgpd
            }

            # Enviar email de boas-vindas ao novo usuário em background (passando base_url)
            threading.Thread(target=enviar_boas_vindas, args=(email, nome, base_url)).start()

            # Notificar todos os admins em background (passando base_url)
            threading.Thread(target=notificar_admins_novo_cadastro, args=(
                nome, cpf, rg, data_nascimento, telefone, whatsapp, email, cep, rua, numero, complemento, ponto_referencia, bairro, plano, vencimento, nome_rede, senha_rede, lgpd, base_url
            )).start()

            # ===== Envio para Telegram em background =====
            def run_telegram_notifications():
                asyncio.run(send_telegram_notifications(cadastro_data))

            threading.Thread(target=run_telegram_notifications).start()

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

@app.route('/check_cobertura', methods=['POST'])
def check_cobertura():
    data = request.get_json(silent=True) or {}
    logradouro = data.get('logradouro', '').strip().lower()
    bairro = data.get('bairro', '').strip().lower()
    cidade = data.get('cidade', '').strip().lower()
    estado = data.get('estado', '').strip().lower()

    if not logradouro or not bairro:
        return jsonify({'coberta': False})

    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        # Consulta flexível: compara nome_logradouro e bairro (case-insensitive)
        # Se precisar incluir tipo_logradouro, adicione na WHERE
        cursor.execute("""
            SELECT COUNT(*) FROM cobertura 
            WHERE LOWER(CONCAT(tipo_logradouro, ' ', nome_logradouro)) = %s 
            AND LOWER(bairro) = %s
        """, (logradouro, bairro))
        exists = cursor.fetchone()[0] > 0
        cursor.close()
        conn.close()
        return jsonify({'coberta': exists})
    except Exception as e:
        print("Erro ao verificar cobertura:", e)
        return jsonify({'coberta': False})

@app.route('/sucesso')
def sucesso():
    return render_template('sucesso.html')  # Crie sucesso.html similar ao PHP

# Nova API para validar cobertura de endereço (baseado em rua existente em cobertura)
@app.route('/validar_cobertura', methods=['POST'])
def validar_cobertura():
    """
    Endpoint para validar se o endereço (rua e opcionalmente bairro) informado consta no banco de dados.
    Assume que se há entradas na tabela cobertura matching, há cobertura.
    Recebe JSON com 'rua' (obrigatório) e 'bairro' (opcional).
    Retorna JSON com 'mensagem' e opcionalmente 'link' se houver cobertura.
    """
    data = request.get_json(silent=True) or {}
    rua_raw = data.get('rua', '') or ''
    bairro_raw = data.get('bairro', '') or ''
    rua = sanitize(rua_raw)
    bairro = sanitize(bairro_raw)

    if not rua:
        return jsonify({'error': 'Rua não informada.'}), 400

    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        # Verifica se existe pelo menos uma entrada com esse logradouro (case-insensitive)
        sql = "SELECT COUNT(*) FROM cobertura WHERE LOWER(CONCAT(tipo_logradouro, ' ', nome_logradouro)) = LOWER(%s)"
        params = (rua,)
        if bairro:
            sql += " AND LOWER(bairro) = LOWER(%s)"
            params += (bairro,)
        cursor.execute(sql, params)
        count = cursor.fetchone()[0]
        cursor.close()
        conn.close()

        if count > 0:
            mensagem = "Parabéns, essa rua possui cobertura da FireNet Telecom. Clique aqui para se cadastrar."
            return jsonify({'mensagem': mensagem, 'link': url_for('cadastro')})
        else:
            mensagem = "Infelizmente essa rua ainda não possui viabilidade técnica para a instalação da FireNet Telecom."
            return jsonify({'mensagem': mensagem})

    except Exception as e:
        print("Erro ao validar cobertura:", e)
        return jsonify({'error': 'Erro interno ao validar cobertura.'}), 500

# Nova rota: Login
@app.route('/login', methods=['GET', 'POST'])
def login():
    if 'user_id' in session:
        return redirect(url_for('clientes'))
    if request.method == 'POST':
        login_input = request.form.get('username')
        password = request.form.get('password')

        if not login_input or not password:
            flash('Preencha todos os campos.', 'danger')
            return render_template('login.html')

        try:
            conn = get_db_connection()
            cursor = conn.cursor()
            cursor.execute("SELECT id, password FROM usuarios WHERE username = %s OR email = %s", (login_input, login_input))
            user = cursor.fetchone()
            cursor.close()
            conn.close()

            if user and check_password_hash(user[1], password):
                session['user_id'] = user[0]
                session.permanent = True  # Marca a sessão como permanente
                return redirect(url_for('clientes'))
            else:
                flash('Credenciais inválidas.', 'danger')
        except Exception as e:
            print("Erro no login:", e)
            flash('Erro interno. Tente novamente.', 'danger')

    return render_template('login.html')

# Nova rota: Logout
@app.route('/logout')
def logout():
    session.clear()  # Limpa toda a sessão
    return redirect(url_for('login'))

# Função helper para checar login
def login_required(view):
    def wrapped_view(**kwargs):
        print(f"Sessão user_id: {session.get('user_id')}")  # Log temporário para debug
        if 'user_id' not in session:
            return redirect(url_for('login'))
        return view(**kwargs)
    wrapped_view.__name__ = view.__name__
    return wrapped_view

# Nova rota: Clientes (lista)
# Atualize a rota para clientes em app.py (assumindo que a rota é def clientes():)
@app.route('/admin/clientes')
@login_required
def clientes():
    per_page = 20
    page = request.args.get('page', 1, type=int)
    offset = (page - 1) * per_page
    busca_nome = request.args.get('busca_nome', '').lower()
    busca_cpf = request.args.get('busca_cpf', '').lower().replace('.', '').replace('-', '')  # Remove máscara para busca

    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)

    # Contar total filtrado
    sql_count = "SELECT COUNT(*) AS total FROM cadastros WHERE 1=1"
    params = []
    if busca_nome:
        sql_count += " AND LOWER(nome) LIKE %s"
        params.append(f"%{busca_nome}%")
    if busca_cpf:
        sql_count += " AND REPLACE(REPLACE(cpf, '.', ''), '-', '') LIKE %s"
        params.append(f"%{busca_cpf}%")
    cursor.execute(sql_count, params)
    total = cursor.fetchone()['total']
    total_pages = (total // per_page) + (1 if total % per_page > 0 else 0)

    params = []  # Reset params para seleção

    # Selecionar filtrado com paginação
    sql_select = """
        SELECT id, nome, cpf, telefone, plano, status_instalacao 
        FROM cadastros 
        WHERE 1=1
    """
    if busca_nome:
        sql_select += " AND LOWER(nome) LIKE %s"
        params.append(f"%{busca_nome}%")
    if busca_cpf:
        sql_select += " AND REPLACE(REPLACE(cpf, '.', ''), '-', '') LIKE %s"
        params.append(f"%{busca_cpf}%")
    sql_select += " ORDER BY id DESC LIMIT %s OFFSET %s"
    params.extend([per_page, offset])
    cursor.execute(sql_select, params)
    cadastros = cursor.fetchall()

    # Formatação de CPF e telefone (faça aqui se necessário, ou no template)

    cursor.close()
    conn.close()

    current_year = datetime.now().year
    return render_template('admin/clientes.html', 
                           cadastros=cadastros, 
                           current_year=current_year,
                           current_page=page,
                           total_pages=total_pages,
                           busca_nome=busca_nome,
                           busca_cpf=busca_cpf)

# Nova rota: Imprimir Ficha
@app.route('/admin/imprimir/<int:cliente_id>')
@login_required
def imprimir_cliente(cliente_id):
    conn = get_db_connection()
    cursor = conn.cursor()
    cursor.execute("SELECT * FROM cadastros WHERE id = %s", (cliente_id,))
    cliente = cursor.fetchone()
    cursor.close()
    conn.close()

    if not cliente:
        flash('Cliente não encontrado.', 'danger')
        return redirect(url_for('clientes'))

    # Formata datas
    created_at = cliente[19].strftime('%d/%m/%Y') if cliente[19] else 'Não definida'  # Ajuste index conforme schema

    return render_template('admin/imprimir_cliente.html', cliente=cliente, created_at=created_at)

# Nova rota para get dados cliente (JSON para modals)
@app.route('/admin/get_cliente/<int:cliente_id>', methods=['GET'])
@login_required
def get_cliente(cliente_id):
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)  # DictCursor para JSON fácil
    cursor.execute("SELECT * FROM cadastros WHERE id = %s", (cliente_id,))
    cliente = cursor.fetchone()
    cursor.close()
    conn.close()
    if cliente:
        return jsonify(cliente)
    return jsonify({'error': 'Cliente não encontrado'}), 404

# Nova rota POST para update geral (sem agendamento)
@app.route('/admin/update_cliente/<int:cliente_id>', methods=['POST'])
@login_required
def update_cliente(cliente_id):
    try:
        # Captura fields gerais
        nome = request.form.get('nome')
        cpf = request.form.get('cpf')
        rg = request.form.get('rg')
        data_nascimento = request.form.get('data_nascimento')
        telefone = request.form.get('telefone')
        whatsapp = request.form.get('whatsapp')
        email = request.form.get('email')
        cep = request.form.get('cep')
        rua = request.form.get('rua')
        numero = request.form.get('numero')
        complemento = request.form.get('complemento')
        ponto_referencia = request.form.get('ponto_referencia')
        bairro = request.form.get('bairro')
        plano = request.form.get('plano')
        vencimento = request.form.get('vencimento')
        nome_rede = request.form.get('nome_rede')
        senha_rede = request.form.get('senha_rede')
        lgpd = request.form.get('lgpd')

        # Validações (adaptadas do cadastro)
        required_fields = [nome, cpf, rg, data_nascimento, telefone, whatsapp, email, cep, rua, numero, ponto_referencia, bairro, plano, vencimento, nome_rede, senha_rede, lgpd]
        if any(field == '' or field is None for field in required_fields):
            return jsonify({'error': 'Todos os campos obrigatórios devem ser preenchidos.'}), 400

        if not validar_cpf(cpf):
            return jsonify({'error': 'CPF inválido.'}), 400

        if not re.match(r'^[\w\.-]+@[\w\.-]+\.\w+$', email):
            return jsonify({'error': 'E-mail inválido.'}), 400

        # ... (adicione outras validações como no cadastro)

        # Converte data para dd/mm/yyyy
        if data_nascimento:
            date_obj = datetime.strptime(data_nascimento, '%Y-%m-%d')
            data_nascimento = date_obj.strftime('%d/%m/%Y')

        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
            UPDATE cadastros SET
            nome=%s, cpf=%s, rg=%s, data_nascimento=%s, telefone=%s, whatsapp=%s,
            email=%s, cep=%s, rua=%s, numero=%s, complemento=%s, ponto_referencia=%s,
            bairro=%s, plano=%s, vencimento=%s, nome_rede=%s, senha_rede=%s, lgpd=%s
            WHERE id=%s
        """, (
            nome, cpf, rg, data_nascimento, telefone, whatsapp, email, cep,
            rua, numero, complemento, ponto_referencia, bairro, plano,
            vencimento, nome_rede, senha_rede, lgpd, cliente_id
        ))
        conn.commit()
        cursor.close()
        conn.close()
        return jsonify({'success': True})
    except ValueError as e:
        return jsonify({'error': str(e)}), 400
    except Exception as e:
        print("Erro ao atualizar cliente:", e)
        return jsonify({'error': 'Erro interno ao atualizar.'}), 500

@app.route('/admin/update_agendamento/<int:cliente_id>', methods=['POST'])
@login_required
def update_agendamento(cliente_id):
    try:
        data_instalacao = request.form.get('data_instalacao')
        turno_instalacao = request.form.get('turno_instalacao')
        status_instalacao = request.form.get('status_instalacao')
        observacoes = request.form.get('observacoes')

        # Converte data para dd/mm/yyyy se fornecida
        if data_instalacao:
            date_obj = datetime.strptime(data_instalacao, '%Y-%m-%d')
            data_instalacao = date_obj.strftime('%d/%m/%Y')

        conn = get_db_connection()
        cursor = conn.cursor(pymysql.cursors.DictCursor)

        # Pega status atual
        cursor.execute("SELECT status_instalacao FROM cadastros WHERE id = %s", (cliente_id,))
        current = cursor.fetchone()
        current_status = current['status_instalacao'] if current else None

        # Verifica se está tentando setar como Concluída (e não era antes)
        if status_instalacao == 'Concluída' and current_status != 'Concluída':
            # Conta concluídas disponíveis (não em relatórios)
            cursor.execute("""
                SELECT COUNT(*) AS total
                FROM cadastros c
                LEFT JOIN relatorios r ON FIND_IN_SET(c.id, r.clientes_ids)
                WHERE c.status_instalacao = 'Concluída'
                  AND r.id IS NULL
            """)
            total_disponiveis = cursor.fetchone()['total']

            if total_disponiveis >= 3:
                cursor.close()
                conn.close()
                return jsonify({'error': 'É necessário emitir o relatório antes de marcar mais instalações como concluídas.'}), 403

        # Prossegue com update
        cursor.execute("""
            UPDATE cadastros SET
                data_instalacao = %s,
                turno_instalacao = %s,
                status_instalacao = %s,
                observacoes = %s
            WHERE id = %s
        """, (data_instalacao, turno_instalacao, status_instalacao, observacoes, cliente_id))
        conn.commit()

        # Busca dados do cliente para notificações
        cursor.execute("SELECT nome, email FROM cadastros WHERE id = %s", (cliente_id,))
        cliente = cursor.fetchone()

        cursor.close()
        conn.close()

        if cliente:
            nome = cliente['nome']
            email = cliente['email']
            base_url = request.url_root or APP_BASE_URL

            # Enviar email de atualização ao cliente em background
            threading.Thread(target=enviar_atualizacao_instalacao, args=(
                email, nome, data_instalacao, turno_instalacao, status_instalacao, observacoes, base_url
            )).start()

            # Envio para Telegram em background
            def run_telegram_notifications():
                asyncio.run(send_telegram_agendamento({
                    'nome': nome,
                    'data_instalacao': data_instalacao,
                    'turno_instalacao': turno_instalacao,
                    'status_instalacao': status_instalacao,
                    'observacoes': observacoes
                }))

            threading.Thread(target=run_telegram_notifications).start()

        return jsonify({'success': True})
    except Exception as e:
        print("Erro ao atualizar agendamento:", e)
        return jsonify({'error': 'Erro interno ao atualizar agendamento'}), 500

# Route para página de usuários (atualize a existente)
@app.route('/admin/usuarios')
@login_required
def admin_usuarios():
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    cursor.execute("SELECT id, username, email, telegram_id FROM usuarios")
    usuarios = cursor.fetchall()
    cursor.close()
    conn.close()
    current_year = datetime.now().year
    return render_template('admin/usuarios.html', usuarios=usuarios, current_year=current_year)

# Route para obter dados de um usuário (JSON)
@app.route('/admin/get_usuario/<int:usuario_id>', methods=['GET'])
@login_required
def get_usuario(usuario_id):
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    cursor.execute("SELECT id, username, email, telegram_id FROM usuarios WHERE id = %s", (usuario_id,))
    usuario = cursor.fetchone()
    cursor.close()
    conn.close()
    if usuario:
        return jsonify(usuario)
    else:
        return jsonify({'error': 'Usuário não encontrado'}), 404

# Route para criar novo usuário
@app.route('/admin/create_usuario', methods=['POST'])
@login_required
def create_usuario():
    try:
        username = request.form.get('username')
        email = request.form.get('email')
        password = request.form.get('password')
        telegram_id = request.form.get('telegram_id') or None

        if not username or not email or not password:
            return jsonify({'error': 'Username, email e senha são obrigatórios'}), 400

        # Valida email
        if not re.match(r'^[\w\.-]+@[\w\.-]+\.\w+$', email):
            return jsonify({'error': 'E-mail inválido'}), 400

        # Hash da senha
        hashed_password = generate_password_hash(password)

        conn = get_db_connection()
        cursor = conn.cursor()

        # Verifica unicidade
        cursor.execute("SELECT COUNT(*) FROM usuarios WHERE username = %s", (username,))
        if cursor.fetchone()[0] > 0:
            cursor.close()
            conn.close()
            return jsonify({'error': 'Username já existe'}), 400

        cursor.execute("SELECT COUNT(*) FROM usuarios WHERE email = %s", (email,))
        if cursor.fetchone()[0] > 0:
            cursor.close()
            conn.close()
            return jsonify({'error': 'Email já existe'}), 400

        # Insere
        cursor.execute("""
            INSERT INTO usuarios (username, password, email, telegram_id)
            VALUES (%s, %s, %s, %s)
        """, (username, hashed_password, email, telegram_id))
        conn.commit()
        cursor.close()
        conn.close()

        return jsonify({'success': True})
    except Exception as e:
        print("Erro ao criar usuário:", e)
        return jsonify({'error': 'Erro interno ao criar usuário'}), 500

# Route para atualizar usuário
@app.route('/admin/update_usuario/<int:usuario_id>', methods=['POST'])
@login_required
def update_usuario(usuario_id):
    try:
        username = request.form.get('username')
        email = request.form.get('email')
        password = request.form.get('password') or None
        telegram_id = request.form.get('telegram_id') or None

        if not username or not email:
            return jsonify({'error': 'Username e email são obrigatórios'}), 400

        # Valida email
        if not re.match(r'^[\w\.-]+@[\w\.-]+\.\w+$', email):
            return jsonify({'error': 'E-mail inválido'}), 400

        conn = get_db_connection()
        cursor = conn.cursor()

        # Verifica unicidade (excluindo o próprio)
        cursor.execute("SELECT COUNT(*) FROM usuarios WHERE username = %s AND id != %s", (username, usuario_id))
        if cursor.fetchone()[0] > 0:
            cursor.close()
            conn.close()
            return jsonify({'error': 'Username já existe'}), 400

        cursor.execute("SELECT COUNT(*) FROM usuarios WHERE email = %s AND id != %s", (email, usuario_id))
        if cursor.fetchone()[0] > 0:
            cursor.close()
            conn.close()
            return jsonify({'error': 'Email já existe'}), 400

        # Prepara update
        update_query = "UPDATE usuarios SET username = %s, email = %s, telegram_id = %s"
        params = [username, email, telegram_id]

        if password:
            hashed_password = generate_password_hash(password)
            update_query += ", password = %s"
            params.append(hashed_password)

        update_query += " WHERE id = %s"
        params.append(usuario_id)

        cursor.execute(update_query, tuple(params))
        conn.commit()
        cursor.close()
        conn.close()

        return jsonify({'success': True})
    except Exception as e:
        print("Erro ao atualizar usuário:", e)
        return jsonify({'error': 'Erro interno ao atualizar usuário'}), 500

# Route para deletar usuário
@app.route('/admin/delete_usuario/<int:usuario_id>', methods=['POST'])
@login_required
def delete_usuario(usuario_id):
    try:
        if usuario_id == session.get('user_id'):
            return jsonify({'error': 'Não é possível deletar o próprio usuário'}), 403

        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("DELETE FROM usuarios WHERE id = %s", (usuario_id,))
        conn.commit()
        cursor.close()
        conn.close()

        return jsonify({'success': True})
    except Exception as e:
        print("Erro ao deletar usuário:", e)
        return jsonify({'error': 'Erro interno ao deletar usuário'}), 500

# Adicione essas rotas em app.py para relatórios
from datetime import datetime

def is_admin(user_id):
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    cursor.execute("SELECT username FROM usuarios WHERE id = %s", (user_id,))
    user = cursor.fetchone()
    cursor.close()
    conn.close()
    return user and user['username'] == 'admin'

@app.route('/admin/relatorios')
@login_required
def admin_relatorios():
    user_id = session.get('user_id')
    admin = is_admin(user_id)

    per_page = 20
    page = request.args.get('page', 1, type=int)
    offset = (page - 1) * per_page

    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)

    # Contar total de relatórios
    cursor.execute("SELECT COUNT(*) AS total FROM relatorios")
    total = cursor.fetchone()['total']
    total_pages = (total // per_page) + (1 if total % per_page > 0 else 0)

    # Relatórios com paginação (ordenados por created_at DESC)
    cursor.execute("""
        SELECT r.*, GROUP_CONCAT(c.nome SEPARATOR ', ') as clientes_nomes
        FROM relatorios r
        LEFT JOIN cadastros c ON FIND_IN_SET(c.id, r.clientes_ids)
        GROUP BY r.id
        ORDER BY r.created_at DESC
        LIMIT %s OFFSET %s
    """, (per_page, offset))
    relatorios_antigos = cursor.fetchall()

    # Concluídas elegíveis (ainda não usadas em nenhum relatório)
    cursor.execute("""
        SELECT COUNT(*) AS total
        FROM cadastros c
        LEFT JOIN relatorios r ON FIND_IN_SET(c.id, r.clientes_ids)
        WHERE c.status_instalacao = 'Concluída'
          AND r.id IS NULL
    """)
    row = cursor.fetchone()
    total_instalacoes = (row['total'] if row and 'total' in row else 0)

    # Sugerir vencimento com base no último relatório (ou próximo mês se não houver)
    # Em app.py, na rota /admin/relatorios
    suggested_vencimento = ''
    cursor.execute("SELECT data_vencimento FROM relatorios ORDER BY created_at DESC LIMIT 1")
    ultimo = cursor.fetchone()
    if ultimo and ultimo.get('data_vencimento'):
        dd, mm, yyyy = ultimo['data_vencimento'].split('/')
        from datetime import date
        d = date(int(yyyy), int(mm), int(dd))
        next_mm = (d.month % 12) + 1
        next_yyyy = d.year + (1 if next_mm == 1 else 0)
        suggested_vencimento = f"{dd}/{next_mm:02d}/{next_yyyy}"
    else:
        today = datetime.now()
        next_mm = (today.month % 12) + 1
        next_yyyy = today.year + (1 if next_mm == 1 else 0)
        suggested_vencimento = f"10/{next_mm:02d}/{next_yyyy}"

    cursor.close()
    conn.close()

    current_year = datetime.now().year
    return render_template('admin/relatorios.html', 
                           relatorios_antigos=relatorios_antigos, 
                           is_admin=admin, 
                           total_instalacoes=total_instalacoes, 
                           suggested_vencimento=suggested_vencimento, 
                           current_year=current_year,
                           current_page=page,
                           total_pages=total_pages)
    
@app.route('/admin/gerar_relatorio', methods=['POST'])
@login_required
def admin_gerar_relatorio():
    user_id = session.get('user_id')
    if not is_admin(user_id):
        flash('Acesso negado.', 'danger')
        return redirect(url_for('admin_relatorios'))

    quantidade = 3  # Fixo em 3, sem opção no form
    data_vencimento = request.form.get('data_vencimento')

    # Validação simples de data dd/mm/yyyy
    if not re.match(r'^\d{2}/\d{2}/\d{4}$', data_vencimento or ''):
        flash('Data de Vencimento inválida (use dd/mm/yyyy).', 'danger')
        return redirect(url_for('admin_relatorios'))

    conn = get_db_connection()
    try:
        cursor = conn.cursor()

        # Seleciona elegíveis: status Concluída, não usados em nenhum relatório,
        # ordenando pelas conclusões mais recentes (data_instalacao é VARCHAR dd/mm/yyyy)
        cursor.execute("""
            SELECT c.id
            FROM cadastros c
            LEFT JOIN relatorios r ON FIND_IN_SET(c.id, r.clientes_ids)
            WHERE c.status_instalacao = 'Concluída'
            AND r.id IS NULL
            AND c.data_instalacao IS NOT NULL
            AND c.data_instalacao <> ''
            ORDER BY STR_TO_DATE(c.data_instalacao, '%%d/%%m/%%Y') DESC
            LIMIT %s
        """, (quantidade,))
        clientes = cursor.fetchall()

        if not clientes or len(clientes) < quantidade:
            flash('Não há instalações suficientes para gerar o relatório.', 'danger')
            return redirect(url_for('admin_relatorios'))

        clientes_ids = ",".join(str(row[0]) for row in clientes)

        # Geração do identificador com proteção a corrida
        identificador = _next_identificador(cursor)

        # Insere o relatório
        cursor.execute("""
            INSERT INTO relatorios (identificador, data_vencimento, clientes_ids)
            VALUES (%s, %s, %s)
        """, (identificador, data_vencimento, clientes_ids))
        conn.commit()

        flash(f"Relatório nº {identificador} gerado com sucesso!", 'success')
        session['novo_relatorio'] = identificador
        return redirect(url_for('admin_relatorios'))

    except Exception as e:
        conn.rollback()
        print("Erro ao gerar relatório:", e)
        flash('Erro interno ao gerar relatório.', 'danger')
        return redirect(url_for('admin_relatorios'))
    finally:
        cursor.close()
        conn.close()


@app.route('/admin/imprimir_relatorio/<identificador>')
@login_required
def admin_imprimir_relatorio(identificador):
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    cursor.execute("""
        SELECT r.*, GROUP_CONCAT(c.nome) as clientes_nomes
        FROM relatorios r
        LEFT JOIN cadastros c ON FIND_IN_SET(c.id, r.clientes_ids)
        WHERE r.identificador = %s
        GROUP BY r.id
    """, (identificador,))
    relatorio = cursor.fetchone()

    if not relatorio:
        flash('Relatório não encontrado.', 'danger')
        return redirect(url_for('admin_relatorios'))

    # Puxar detalhes dos clientes
    clientes_ids = relatorio['clientes_ids'].split(',')
    clientes = []
    for cid in clientes_ids:
        cursor.execute("""
            SELECT nome, cpf, rua, numero, complemento, bairro, cep, data_instalacao
            FROM cadastros WHERE id = %s
        """, (cid,))
        cliente = cursor.fetchone()
        if cliente:
            clientes.append(cliente)

    # Data criação formatada
    data = relatorio['created_at']
    meses = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro']
    dia = data.day
    mes = meses[data.month - 1]
    ano = data.year
    data_criacao = f"Rio de Janeiro, {dia} de {mes} de {ano}"

    cursor.close()
    conn.close()

    return render_template('admin/imprimir_relatorio.html', 
                           identificador=identificador, 
                           data_vencimento=relatorio['data_vencimento'], 
                           clientes=clientes, 
                           data_criacao=data_criacao, 
                           quantidade=3)

@app.route('/admin/cobertura')
@login_required
def admin_cobertura():
    per_page = 20
    page = request.args.get('page', 1, type=int)
    offset = (page - 1) * per_page
    busca_nome_logradouro = request.args.get('busca_nome_logradouro', '').lower()
    busca_bairro = request.args.get('busca_bairro', '').lower()

    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)

    # Contar total filtrado
    sql_count = "SELECT COUNT(*) AS total FROM cobertura WHERE 1=1"
    params = []
    if busca_nome_logradouro:
        sql_count += " AND LOWER(nome_logradouro) LIKE %s"
        params.append(f"%{busca_nome_logradouro}%")
    if busca_bairro:
        sql_count += " AND LOWER(bairro) LIKE %s"
        params.append(f"%{busca_bairro}%")
    cursor.execute(sql_count, params)
    total = cursor.fetchone()['total']
    total_pages = (total // per_page) + (1 if total % per_page > 0 else 0)

    params = []  # Reset params para a query de seleção

    # Selecionar filtrado com paginação
    sql_select = """
        SELECT id, tipo_logradouro, nome_logradouro, bairro, cidade, estado, pais 
        FROM cobertura 
        WHERE 1=1
    """
    if busca_nome_logradouro:
        sql_select += " AND LOWER(nome_logradouro) LIKE %s"
        params.append(f"%{busca_nome_logradouro}%")
    if busca_bairro:
        sql_select += " AND LOWER(bairro) LIKE %s"
        params.append(f"%{busca_bairro}%")
    sql_select += " ORDER BY id DESC LIMIT %s OFFSET %s"
    params.extend([per_page, offset])
    cursor.execute(sql_select, params)
    coberturas = cursor.fetchall()

    cursor.close()
    conn.close()

    current_year = datetime.now().year
    return render_template('admin/cobertura.html', 
                           coberturas=coberturas, 
                           current_year=current_year,
                           current_page=page,
                           total_pages=total_pages,
                           busca_nome_logradouro=busca_nome_logradouro,
                           busca_bairro=busca_bairro)

# Route para obter dados de uma cobertura (JSON)
@app.route('/admin/get_cobertura/<int:cobertura_id>', methods=['GET'])
@login_required
def get_cobertura(cobertura_id):
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    cursor.execute("SELECT id, tipo_logradouro, nome_logradouro, bairro, cidade, estado, pais FROM cobertura WHERE id = %s", (cobertura_id,))
    cobertura = cursor.fetchone()
    cursor.close()
    conn.close()
    if cobertura:
        return jsonify(cobertura)
    else:
        return jsonify({'error': 'Endereço não encontrado'}), 404

# Route para criar novo endereço de cobertura
@app.route('/admin/create_cobertura', methods=['POST'])
@login_required
def create_cobertura():
    try:
        tipo_logradouro = request.form.get('tipo_logradouro')
        nome_logradouro = request.form.get('nome_logradouro')
        bairro = request.form.get('bairro')
        cidade = request.form.get('cidade')
        estado = request.form.get('estado')
        pais = request.form.get('pais')

        if not all([tipo_logradouro, nome_logradouro, bairro, cidade, estado, pais]):
            return jsonify({'error': 'Todos os campos são obrigatórios'}), 400

        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
            INSERT INTO cobertura (tipo_logradouro, nome_logradouro, bairro, cidade, estado, pais)
            VALUES (%s, %s, %s, %s, %s, %s)
        """, (tipo_logradouro, nome_logradouro, bairro, cidade, estado, pais))
        conn.commit()
        cursor.close()
        conn.close()

        return jsonify({'success': True})
    except Exception as e:
        print("Erro ao criar endereço de cobertura:", e)
        return jsonify({'error': 'Erro interno ao criar endereço'}), 500

# Route para atualizar endereço de cobertura
@app.route('/admin/update_cobertura/<int:cobertura_id>', methods=['POST'])
@login_required
def update_cobertura(cobertura_id):
    try:
        tipo_logradouro = request.form.get('tipo_logradouro')
        nome_logradouro = request.form.get('nome_logradouro')
        bairro = request.form.get('bairro')
        cidade = request.form.get('cidade')
        estado = request.form.get('estado')
        pais = request.form.get('pais')

        if not all([tipo_logradouro, nome_logradouro, bairro, cidade, estado, pais]):
            return jsonify({'error': 'Todos os campos são obrigatórios'}), 400

        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
            UPDATE cobertura SET tipo_logradouro = %s, nome_logradouro = %s, bairro = %s, cidade = %s, estado = %s, pais = %s
            WHERE id = %s
        """, (tipo_logradouro, nome_logradouro, bairro, cidade, estado, pais, cobertura_id))
        conn.commit()
        cursor.close()
        conn.close()

        return jsonify({'success': True})
    except Exception as e:
        print("Erro ao atualizar endereço de cobertura:", e)
        return jupytext({'error': 'Erro interno ao atualizar endereço'}), 500

# Route para deletar endereço de cobertura
@app.route('/admin/delete_cobertura/<int:cobertura_id>', methods=['POST'])
@login_required
def delete_cobertura(cobertura_id):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("DELETE FROM cobertura WHERE id = %s", (cobertura_id,))
        conn.commit()
        cursor.close()
        conn.close()

        return jsonify({'success': True})
    except Exception as e:
        print("Erro ao deletar endereço de cobertura:", e)
        return jsonify({'error': 'Erro interno ao deletar endereço'}), 500

@app.route('/forgot_password', methods=['GET', 'POST'])
def forgot_password():
    if request.method == 'POST':
        email = request.form.get('email')
        if not email:
            flash('Informe o e-mail.', 'danger')
            return render_template('forgot_password.html')

        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT id FROM usuarios WHERE email = %s", (email,))
        user = cursor.fetchone()
        if not user:
            flash('E-mail não encontrado.', 'danger')
            return render_template('forgot_password.html')

        # Gera token
        token = str(uuid.uuid4())
        cursor.execute("""
            INSERT INTO password_resets (email, token)
            VALUES (%s, %s)
        """, (email, token))
        conn.commit()
        cursor.close()
        conn.close()

        # Envia email em background
        base_url = request.url_root or APP_BASE_URL
        threading.Thread(target=enviar_reset_senha, args=(email, token, base_url)).start()

        flash('Link de reset enviado para o e-mail.', 'success')
        return redirect(url_for('login'))

    return render_template('forgot_password.html')

@app.route('/reset_password/<token>', methods=['GET', 'POST'])
def reset_password(token):
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    cursor.execute("""
        SELECT id, email, created_at FROM password_resets 
        WHERE token = %s
    """, (token,))
    reset = cursor.fetchone()

    if not reset or (datetime.now() - reset['created_at'] > timedelta(hours=1)):
        flash('Token inválido ou expirado.', 'danger')
        cursor.close()
        conn.close()
        return redirect(url_for('login'))

    if request.method == 'POST':
        password = request.form.get('password')
        confirm_password = request.form.get('confirm_password')
        if not password or password != confirm_password:
            flash('Senhas não coincidem ou vazias.', 'danger')
            return render_template('reset_password.html', token=token)

        hashed_password = generate_password_hash(password)
        cursor.execute("""
            UPDATE usuarios SET password = %s WHERE email = %s
        """, (hashed_password, reset['email']))
        cursor.execute("DELETE FROM password_resets WHERE id = %s", (reset['id'],))
        conn.commit()
        cursor.close()
        conn.close()

        flash('Senha atualizada com sucesso.', 'success')
        return redirect(url_for('login'))

    cursor.close()
    conn.close()
    return render_template('reset_password.html', token=token)

@app.route('/enviar_contato', methods=['POST'])
def enviar_contato_route():
    nome = request.form.get('nome')
    email = request.form.get('email')
    setor = request.form.get('setor')
    mensagem = request.form.get('mensagem')
    recaptcha_token = request.form.get('g-recaptcha-response')

    # Validação básica
    if not all([nome, email, setor, mensagem, recaptcha_token]):
        flash('Todos os campos são obrigatórios.', 'danger')
        return redirect(url_for('contato'))

    # Verificar reCAPTCHA
    recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify'
    recaptcha_data = {
        'secret': app.config['RECAPTCHA_SECRET_KEY'],
        'response': recaptcha_token
    }
    recaptcha_response = requests.post(recaptcha_url, data=recaptcha_data).json()
    if not recaptcha_response.get('success'):
        flash('Falha na verificação do reCAPTCHA. Tente novamente.', 'danger')
        return redirect(url_for('contato'))

    # Mapear setor para e-mail destinatário
    emails_setor = {
        'Suporte': 'suporte@firenettelecom.online',
        'Vendas': 'vendas@firenettelecom.online',
        'Financeiro': 'financeiro@firenettelecom.online'
    }
    destinatario = emails_setor.get(setor)
    if not destinatario:
        flash('Setor inválido.', 'danger')
        return redirect(url_for('contato'))

    # Enviar e-mail
    try:
        base_url = request.url_root or APP_BASE_URL
        current_year = datetime.now().year
        msg = Message(f'Contato via Site - {setor}', recipients=[destinatario])
        msg.reply_to = email
        msg.html = render_template('emails/contato.html',  # Crie um template emails/contato.html com os dados
                                   nome=nome, email=email, setor=setor, mensagem=mensagem,
                                   current_year=current_year, base_url=base_url)
        mail.send(msg)
        flash('Mensagem enviada com sucesso! Entraremos em contato em breve.', 'success')
    except Exception as e:
        print(f"Erro ao enviar contato: {e}")
        flash('Erro ao enviar a mensagem. Tente novamente mais tarde.', 'danger')

    return redirect(url_for('contato'))

# Função para email de reset (adicionada ao final, com outras funções de email)
def enviar_reset_senha(email_destino, token, base_url):
    with app.app_context():
        reset_url = f"{base_url}reset_password/{token}"
        current_year = datetime.now().year
        msg = Message('Reset de Senha - FireNet Telecom', recipients=[email_destino])
        msg.reply_to = app.config['REPLY_TO_EMAIL']
        msg.html = render_template('emails/reset_senha.html', reset_url=reset_url, base_url=base_url, current_year=current_year)
        try:
            mail.send(msg)
        except Exception as e:
            print(f"Erro ao enviar email de reset para {email_destino}: {e}")

# Função para enviar email de boas-vindas ao usuário (adicionado base_url)
def enviar_boas_vindas(email_destino, nome, base_url):
    with app.app_context():
        current_year = datetime.now().year
        msg = Message('Boas-vindas à FireNet Telecom', recipients=[email_destino])
        msg.reply_to = app.config['REPLY_TO_EMAIL']
        msg.html = render_template('emails/boas_vindas.html', nome=nome, current_year=current_year, base_url=base_url)
        try:
            mail.send(msg)
        except Exception as e:
            print(f"Erro ao enviar email de boas-vindas para {email_destino}: {e}")

# Função para notificar admins sobre novo cadastro (adicionado base_url)
def notificar_admins_novo_cadastro(nome, cpf, rg, data_nascimento, telefone, whatsapp, email, cep, rua, numero, complemento, ponto_referencia, bairro, plano, vencimento, nome_rede, senha_rede, lgpd, base_url):
    with app.app_context():
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT email FROM usuarios")
        admins = [row[0] for row in cursor.fetchall()]
        cursor.close()
        conn.close()

        current_year = datetime.now().year
        data_cadastro = datetime.now().strftime('%d/%m/%Y %H:%M:%S')  # Aproximação, ou fetch do DB se preferir

        for admin_email in admins:
            msg = Message('Novo Cadastro na FireNet Telecom', recipients=[admin_email])
            msg.reply_to = app.config['REPLY_TO_EMAIL']
            msg.html = render_template('emails/novo_cadastro_admin.html', 
                                       nome=nome, cpf=cpf, rg=rg, data_nascimento=data_nascimento,
                                       telefone=telefone, whatsapp=whatsapp, email=email, cep=cep,
                                       rua=rua, numero=numero, complemento=complemento,
                                       ponto_referencia=ponto_referencia, bairro=bairro,
                                       plano=plano, vencimento=vencimento, nome_rede=nome_rede,
                                       senha_rede=senha_rede, lgpd=lgpd, data_cadastro=data_cadastro,
                                       current_year=current_year, base_url=base_url)
            try:
                mail.send(msg)
            except Exception as e:
                print(f"Erro ao notificar admin {admin_email}: {e}")

# Função async para Telegram
async def send_telegram_notifications(cadastro_data):
    token = os.environ.get('TELEGRAM_BOT_TOKEN')
    if not token:
        print("TELEGRAM_BOT_TOKEN não configurado. Ignorando envio.")
        return

    bot = Bot(token=token)

    # Busca todos os telegram_ids (exclui nulos)
    conn_notify = get_db_connection()
    cursor_notify = conn_notify.cursor()
    cursor_notify.execute("SELECT telegram_id FROM usuarios WHERE telegram_id IS NOT NULL")
    telegram_ids = [row[0] for row in cursor_notify.fetchall()]
    cursor_notify.close()
    conn_notify.close()

    if not telegram_ids:
        print("Nenhum telegram_id encontrado. Ignorando envio.")
        return

    # Formata a mensagem com TODOS os dados (em Markdown para melhor leitura)
    message = (
        "*Novo Cadastro na FireNet Telecom*\n\n"
        f"*Nome:* {cadastro_data['nome']}\n"
        f"*CPF:* {cadastro_data['cpf']}\n"
        f"*RG:* {cadastro_data['rg']}\n"
        f"*Data de Nascimento:* {cadastro_data['data_nascimento']}\n"
        f"*Telefone:* {cadastro_data['telefone']}\n"
        f"*WhatsApp:* {cadastro_data['whatsapp']}\n"
        f"*E-mail:* {cadastro_data['email']}\n"
        f"*CEP:* {cadastro_data['cep']}\n"
        f"*Rua:* {cadastro_data['rua']}\n"
        f"*Número:* {cadastro_data['numero']}\n"
        f"*Complemento:* {cadastro_data['complemento'] or 'N/A'}\n"
        f"*Ponto de Referência:* {cadastro_data['ponto_referencia']}\n"
        f"*Bairro:* {cadastro_data['bairro']}\n"
        f"*Plano:* {cadastro_data['plano']}\n"
        f"*Vencimento:* {cadastro_data['vencimento']}\n"
        f"*Nome da Rede:* {cadastro_data['nome_rede']}\n"
        f"*Senha da Rede:* {cadastro_data['senha_rede']}\n"  # Cuidado: senha sensível! Considere mascarar se for produção
        f"*LGPD:* {cadastro_data['lgpd']}\n"
        f"*Data de Cadastro:* {datetime.now().strftime('%d/%m/%Y %H:%M:%S')}\n\n"
        "Por favor, entre em contato com o cliente."
    )

    for chat_id in telegram_ids:
        try:
            await bot.send_message(chat_id=chat_id, text=message, parse_mode='Markdown')
            print(f"Mensagem enviada para {chat_id}")
        except TelegramError as e:
            print(f"Erro ao enviar para {chat_id}: {e}")

# Função para enviar email de atualização de instalação ao cliente (adicionado base_url)
def enviar_atualizacao_instalacao(email_destino, nome, data_instalacao, turno_instalacao, status_instalacao, observacoes, base_url):
    with app.app_context():
        current_year = datetime.now().year
        msg = Message('Atualização de Instalação - FireNet Telecom', recipients=[email_destino])
        msg.reply_to = app.config['REPLY_TO_EMAIL']
        msg.html = render_template('emails/atualizacao_instalacao.html', 
                                   nome=nome, 
                                   data_instalacao=data_instalacao, 
                                   turno_instalacao=turno_instalacao, 
                                   status_instalacao=status_instalacao, 
                                   observacoes=observacoes, 
                                   current_year=current_year,
                                   base_url=base_url)
        try:
            mail.send(msg)
        except Exception as e:
            print(f"Erro ao enviar email de atualização para {email_destino}: {e}")

if __name__ == '__main__':
    if wait_for_db():
        init_db()
        app.run(host='0.0.0.0', port=5000, debug=False)
    else:
        raise RuntimeError("Não foi possível iniciar o aplicativo: falha na conexão com o banco de dados.")