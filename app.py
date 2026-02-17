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
import json # <--- Novo import necessário

app = Flask(__name__)

@app.context_processor
def inject_version():
    version = os.environ.get('APP_VERSION', 'dev-local')
    return dict(app_version=version)

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
app.config['RECAPTCHA_ENABLED'] = os.environ.get('ENABLE_RECAPTCHA', 'True') == 'True'

# Configurações não-DB
GOOGLE_MAPS_API_KEY = os.environ.get('GOOGLE_MAPS_API_KEY', '')

# Fallback para base_url via env var (útil para testes ou crons sem request context)
APP_BASE_URL = os.environ.get('APP_BASE_URL', 'http://localhost:5000/')

# --- NOVA FUNÇÃO DE LÓGICA DE HORÁRIO ---
def is_horario_comercial():
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT valor FROM configuracoes WHERE chave = 'horario_atendimento'")
        row = cursor.fetchone()
        cursor.close()
        conn.close()

        if not row:
            return False 

        schedule = json.loads(row[0])
        agora = datetime.now()
        hoje_str = agora.strftime('%Y-%m-%d') # Formato AAAA-MM-DD
        hora_atual = agora.strftime('%H:%M')
        
        # 1. Verifica se hoje é um feriado/data específica
        feriados = schedule.get('feriados', [])
        regra_hoje = next((item for item in feriados if item['data'] == hoje_str), None)

        if regra_hoje:
            # Se a data existe na lista, segue a regra dela (aberto ou fechado)
            if not regra_hoje.get('ativo'):
                return False
            start = regra_hoje.get('inicio', '00:00')
            end = regra_hoje.get('fim', '23:59')
            return start <= hora_atual < end

        # 2. Se não for feriado, segue a regra da semana
        dia_semana = agora.weekday() # 0=Segunda ... 6=Domingo
        config_dia = None

        if 0 <= dia_semana <= 4: # Seg a Sex
            config_dia = schedule.get('weekdays')
        elif dia_semana == 5: # Sábado
            config_dia = schedule.get('saturday')
        elif dia_semana == 6: # Domingo
            config_dia = schedule.get('sunday')

        if config_dia and config_dia.get('active'):
            start = config_dia.get('start', '00:00')
            end = config_dia.get('end', '23:59')
            return start <= hora_atual < end
            
        return False 

    except Exception as e:
        print(f"Erro ao verificar horário: {e}")
        return False

# Em app.py

# Em app.py

def obter_mensagem_indisponibilidade():
    """
    Gera mensagem de indisponibilidade com Motivo e Preposição personalizados.
    Ex: "Por motivo de Natal" ou "Por motivo do Carnaval"
    """
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("SELECT valor FROM configuracoes WHERE chave = 'horario_atendimento'")
        row = cursor.fetchone()
        cursor.close()
        conn.close()

        if not row:
            return "Consulte nosso horário de atendimento."

        schedule = json.loads(row[0])
        agora = datetime.now()
        hoje_str = agora.strftime('%Y-%m-%d')
        hora_atual = agora.strftime('%H:%M')
        dia_semana = agora.weekday()

        # 1. VERIFICA FERIADOS (Prioridade Máxima)
        feriados = schedule.get('feriados', [])
        feriado_hoje = next((item for item in feriados if item['data'] == hoje_str), None)

        if feriado_hoje:
            # Pega motivo e preposição (com defaults)
            motivo = feriado_hoje.get('motivo', 'feriado')
            prep = feriado_hoje.get('preposicao', 'de') 
            
            # Monta o prefixo: "Por motivo do Carnaval"
            prefixo = f"Por motivo {prep} {motivo}"

            if not feriado_hoje.get('ativo'):
                return f"{prefixo}, hoje não haverá atendimento."
            else:
                inicio = feriado_hoje.get('inicio', '00:00')
                fim = feriado_hoje.get('fim', '23:59')
                
                if hora_atual > fim:
                    return f"{prefixo}, nosso atendimento se encerrou às {fim}."
                elif hora_atual < inicio:
                    return f"{prefixo}, nosso atendimento iniciará às {inicio}."

        # 2. LÓGICA PADRÃO (Semana/Fim de semana) - Sem alterações aqui
        wd = schedule.get('weekdays', {})
        sat = schedule.get('saturday', {})
        sun = schedule.get('sunday', {})

        w_start = wd.get('start', '09:00')
        w_end = wd.get('end', '18:00')
        w_active = wd.get('active', True)

        s_start = sat.get('start', '09:00')
        s_end = sat.get('end', '13:00')
        s_active = sat.get('active', True)

        su_start = sun.get('start', '09:00')
        su_end = sun.get('end', '13:00')
        su_active = sun.get('active', False)

        msg_horarios = ""

        if w_active and s_active and su_active and (w_start == s_start == su_start) and (w_end == s_end == su_end):
            msg_horarios = f"de segunda a domingo é das {w_start} às {w_end}."
        elif w_active and s_active and (w_start == s_start) and (w_end == s_end):
            msg_horarios = f"de segunda a sábado é das {w_start} às {w_end}."
            if su_active:
                msg_horarios += f" E aos domingos, das {su_start} às {su_end}."
        elif w_active:
            msg_horarios = f"de segunda a sexta é das {w_start} às {w_end}."
            if s_active and su_active and (s_start == su_start) and (s_end == su_end):
                msg_horarios += f" E aos fins de semana, das {s_start} às {s_end}."
            else:
                if s_active:
                    conector = " Aos" if su_active else " E aos"
                    msg_horarios += f"{conector} sábados, das {s_start} às {s_end}."
                if su_active:
                    msg_horarios += f" E aos domingos, das {su_start} às {su_end}."
        else:
             partes = []
             if s_active: partes.append(f"aos sábados das {s_start} às {s_end}")
             if su_active: partes.append(f"aos domingos das {su_start} às {su_end}")
             if partes: msg_horarios = " e ".join(partes) + "."

        config_dia = None
        if 0 <= dia_semana <= 4: config_dia = wd
        elif dia_semana == 5: config_dia = sat
        elif dia_semana == 6: config_dia = sun

        if not config_dia or not config_dia.get('active'):
            return f"Hoje não haverá expediente. Nosso funcionamento {msg_horarios}"

        start = config_dia.get('start', '00:00')
        end = config_dia.get('end', '23:59')

        if hora_atual > end or hora_atual < start:
             return f"No momento, estamos fora do horário de atendimento. Nosso funcionamento {msg_horarios}"

        return "Atendimento temporariamente indisponível."

    except Exception as e:
        print(f"Erro msg indisponibilidade: {e}")
        return "No momento, estamos fora do horário de atendimento."

# --- FUNÇÃO AUXILIAR PARA AVISOS ---
# --- FUNÇÃO AUXILIAR PARA AVISOS (ATUALIZADA) ---
def get_aviso_ativo():
    try:
        conn = get_db_connection()
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        
        agora = datetime.now()
        
        # Busca o aviso que está ATIVO E DENTRO DO PRAZO EXATO NESTE SEGUNDO
        # Tratamos NULL como infinito (inicio NULL = sempre começou, fim NULL = nunca acaba)
        query = """
            SELECT * FROM avisos 
            WHERE ativo = 1 
            AND (data_inicio IS NULL OR data_inicio <= %s) 
            AND (data_fim IS NULL OR data_fim >= %s)
            ORDER BY id DESC LIMIT 1
        """
        cursor.execute(query, (agora, agora))
        aviso = cursor.fetchone()
        cursor.close()
        conn.close()
        return aviso
    except Exception as e:
        print(f"Erro ao buscar aviso: {e}")
        return None

# --- FUNÇÃO PARA VERIFICAR CONFLITO DE HORÁRIO ---
def check_conflict(start, end, exclude_id=None):
    """
    Verifica se existe algum aviso ATIVO que conflite com o período start-end.
    Retorna True se houver conflito, False se estiver livre.
    """
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Define datas "infinitas" para comparação se vier None
        # Se start for None (Imediato), consideramos ano 2000
        # Se end for None (Indeterminado), consideramos ano 2100
        s_check = start if start else datetime(2000, 1, 1)
        e_check = end if end else datetime(2100, 1, 1)

        # Query inteligente de sobreposição:
        # (StartA < EndB) AND (EndA > StartB)
        query = """
            SELECT id, data_inicio, data_fim FROM avisos 
            WHERE ativo = 1 
            AND id != %s
        """
        params = [exclude_id if exclude_id else -1]
        
        cursor.execute(query, params)
        avisos_ativos = cursor.fetchall()
        cursor.close()
        conn.close()

        for row in avisos_ativos:
            # Recupera datas do banco, tratando NULLs
            db_start = row[1] if row[1] else datetime(2000, 1, 1)
            db_end = row[2] if row[2] else datetime(2100, 1, 1)
            
            # Lógica de colisão
            if s_check < db_end and e_check > db_start:
                return True # Conflitou!

        return False
    except Exception as e:
        print(f"Erro ao verificar conflito: {e}")
        return True # Na dúvida, bloqueia

@app.context_processor
def inject_global_vars():
    """
    Injeta variáveis globais em todos os templates (WhatsApp, Horários e Avisos).
    """
    whatsapp_ativo = False
    modo_automatico = False
    schedule_settings = {}
    aviso_global = None # Inicializa como None por segurança
    
    # Valores padrão de telefone
    whatsapp_sales = os.environ.get('WHATSAPP_SALES', '5521981176211')
    whatsapp_support = os.environ.get('WHATSAPP_SUPPORT', '5521981176211')
    mensagem_indisponibilidade = "Fora do horário de atendimento."

    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # 1. Carrega Configurações Gerais
        cursor.execute("SELECT chave, valor FROM configuracoes")
        configs = {row[0]: row[1] for row in cursor.fetchall()}
        
        # 2. Lógica de Horário / WhatsApp
        modo_automatico = (configs.get('modo_automatico', '0') == '1')
        status_manual = (configs.get('whatsapp_ativo', '0') == '1')

        # Tenta decodificar o JSON do horário
        try:
            schedule_settings = json.loads(configs.get('horario_atendimento', '{}'))
        except:
            schedule_settings = {}

        if modo_automatico:
            whatsapp_ativo = is_horario_comercial()
        else:
            whatsapp_ativo = status_manual
            
        # Gera mensagem de indisponibilidade se estiver fechado
        if not whatsapp_ativo:
            mensagem_indisponibilidade = obter_mensagem_indisponibilidade()

        cursor.close()
        conn.close()
        
        # 3. Busca Aviso Global (usando a função que criamos acima)
        aviso_global = get_aviso_ativo()
        
    except Exception as e:
        print(f"Erro no context processor: {e}")
        # Mantém valores seguros em caso de erro no banco
        whatsapp_ativo = True 

    return dict(
        whatsapp_ativo=whatsapp_ativo,
        modo_automatico=modo_automatico,
        schedule=schedule_settings,
        whatsapp_sales=whatsapp_sales,
        whatsapp_support=whatsapp_support,
        mensagem_indisponibilidade=mensagem_indisponibilidade,
        aviso_global=aviso_global  # <--- Aqui está a variável mágica para o modal!
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

            if plano not in ['500 MEGAS', '700 MEGAS', '1 GIGA']:
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

@app.route('/login', methods=['GET', 'POST'])
def login():
    if 'user_id' in session:
        return redirect(url_for('clientes'))
    
    if request.method == 'POST':
        # LÓGICA NOVA: Verifica se o Captcha está habilitado antes de validar
        if app.config['RECAPTCHA_ENABLED']:
            recaptcha_response = request.form.get('g-recaptcha-response')
            if not recaptcha_response:
                flash('Por favor, complete o reCAPTCHA.', 'danger')
                return redirect(url_for('login'))
            
            secret_key = app.config['RECAPTCHA_SECRET_KEY']
            payload = {
                'secret': secret_key,
                'response': recaptcha_response,
                'remoteip': request.remote_addr
            }
            try:
                response = requests.post('https://www.google.com/recaptcha/api/siteverify', data=payload)
                result = response.json()
                if not result.get('success'):
                    flash('Falha na verificação do reCAPTCHA. Tente novamente.', 'danger')
                    return redirect(url_for('login'))
            except Exception:
                flash('Erro ao conectar com reCAPTCHA.', 'danger')
                return redirect(url_for('login'))

        # Lógica de login padrão (continua igual)
        username = request.form['username'].strip().lower()
        password = request.form['password']
        try:
            conn = get_db_connection()
            cursor = conn.cursor(pymysql.cursors.DictCursor)
            cursor.execute("""
                SELECT id, username, email, password
                FROM usuarios
                WHERE LOWER(username) = %s OR LOWER(email) = %s
            """, (username, username))
            user = cursor.fetchone()
            cursor.close()
            conn.close()
            if user and check_password_hash(user['password'], password):
                session['user_id'] = user['id']
                session.permanent = True
                return redirect(url_for('clientes'))
            else:
                flash('Credenciais inválidas.', 'danger')
        except Exception as e:
            print("Erro no login:", e)
            flash('Erro interno. Tente novamente.', 'danger')

    # Passa a variável recaptcha_enabled para o template
    return render_template('login.html', 
                           recaptcha_site_key=app.config['RECAPTCHA_SITE_KEY'],
                           recaptcha_enabled=app.config['RECAPTCHA_ENABLED'])

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

@app.route('/admin/clientes')
@login_required
def clientes():
    per_page = 20
    page = request.args.get('page', 1, type=int)
    offset = (page - 1) * per_page
    busca_nome = request.args.get('busca_nome', '').lower()
    busca_cpf = request.args.get('busca_cpf', '').lower().replace('.', '').replace('-', '') 

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

    params = [] 

    # --- ALTERAÇÃO AQUI: Adicionado data_instalacao e turno_instalacao ---
    sql_select = """
        SELECT id, nome, cpf, telefone, plano, status_instalacao, data_instalacao, turno_instalacao
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

# Em app.py, substitua a rota '/admin/imprimir/<int:cliente_id>' por esta:

@app.route('/admin/imprimir/<int:cliente_id>')
@login_required
def imprimir_cliente(cliente_id):
    conn = get_db_connection()
    # MUDANÇA IMPORTANTE: DictCursor para acessar por nome (cliente['nome'])
    cursor = conn.cursor(pymysql.cursors.DictCursor) 
    cursor.execute("SELECT * FROM cadastros WHERE id = %s", (cliente_id,))
    cliente = cursor.fetchone()
    cursor.close()
    conn.close()

    if not cliente:
        flash('Cliente não encontrado.', 'danger')
        return redirect(url_for('clientes'))

    # Data de emissão atual para o cabeçalho
    current_date = datetime.now().strftime('%d/%m/%Y')

    return render_template('admin/imprimir_cliente.html', cliente=cliente, current_date=current_date)

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
    
    # Filtros
    data_inicio = request.args.get('data_inicio')
    data_fim = request.args.get('data_fim')
    status_filtro = request.args.get('status') # Opcional, se quiser filtrar visualmente

    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)

    # 1. Contadores para os Badges do Topo (Estatísticas Gerais)
    cursor.execute("SELECT status_instalacao, COUNT(*) as count FROM cadastros GROUP BY status_instalacao")
    stats = {row['status_instalacao']: row['count'] for row in cursor.fetchall()}
    total_concluidas = stats.get('Concluída', 0)
    total_programadas = stats.get('Programada', 0)
    total_nao_realizadas = stats.get('Não Realizada', 0)

    # 2. Query Base de Relatórios
    sql_base = """
        SELECT r.*, GROUP_CONCAT(c.nome SEPARATOR ', ') as clientes_nomes
        FROM relatorios r
        LEFT JOIN cadastros c ON FIND_IN_SET(c.id, r.clientes_ids)
        WHERE 1=1
    """
    params = []

    if data_inicio:
        sql_base += " AND r.created_at >= %s"
        params.append(f"{data_inicio} 00:00:00")
    if data_fim:
        sql_base += " AND r.created_at <= %s"
        params.append(f"{data_fim} 23:59:59")
    
    sql_base += " GROUP BY r.id ORDER BY r.created_at DESC"

    # Paginação
    sql_paginated = sql_base + " LIMIT %s OFFSET %s"
    params_paginated = params + [per_page, offset]

    cursor.execute(sql_paginated, params_paginated)
    relatorios_antigos = cursor.fetchall()

    # Contar total para paginação
    cursor.execute(f"SELECT COUNT(*) as total FROM ({sql_base}) as sub", params)
    total = cursor.fetchone()['total']
    total_pages = (total // per_page) + (1 if total % per_page > 0 else 0)

    # 3. Lógica do "Gatilho" (Instalações Concluídas Pendentes de Relatório)
    cursor.execute("""
        SELECT COUNT(*) AS total
        FROM cadastros c
        LEFT JOIN relatorios r ON FIND_IN_SET(c.id, r.clientes_ids)
        WHERE c.status_instalacao = 'Concluída'
          AND r.id IS NULL
    """)
    row = cursor.fetchone()
    total_instalacoes = (row['total'] if row and 'total' in row else 0)

    # 4. Sugestão de Vencimento
    suggested_vencimento = ''
    cursor.execute("SELECT data_vencimento FROM relatorios ORDER BY created_at DESC LIMIT 1")
    ultimo = cursor.fetchone()
    if ultimo and ultimo.get('data_vencimento'):
        try:
            dd, mm, yyyy = ultimo['data_vencimento'].split('/')
            from datetime import date
            d = date(int(yyyy), int(mm), int(dd))
            next_mm = (d.month % 12) + 1
            next_yyyy = d.year + (1 if next_mm == 1 else 0)
            suggested_vencimento = f"{dd}/{next_mm:02d}/{next_yyyy}"
        except:
             suggested_vencimento = datetime.now().strftime('%d/%m/%Y')
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
                           total_instalacoes=total_instalacoes, # Quantas pendentes
                           total_concluidas=total_concluidas,
                           total_programadas=total_programadas,
                           total_nao_realizadas=total_nao_realizadas,
                           suggested_vencimento=suggested_vencimento,
                           current_year=current_year,
                           current_page=page,
                           total_pages=total_pages,
                           data_inicio=data_inicio,
                           data_fim=data_fim,
                           status_filtro=status_filtro)

# --- FUNÇÃO AUXILIAR PARA VERIFICAR CONFLITO DE HORÁRIO ---
def check_conflict(start, end, exclude_id=None):
    """
    Verifica se existe algum aviso ATIVO que conflite com o período start-end.
    Retorna True se houver conflito, False se estiver livre.
    Trata datas NULL como 'Infinito' (Início NULL = Desde sempre, Fim NULL = Para sempre).
    """
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Datas de referência para "Infinito"
        min_date = datetime(2000, 1, 1)
        max_date = datetime(2100, 1, 1)

        # Define o intervalo do aviso que estamos TENTANDO salvar/ativar
        s_check = start if start else min_date
        e_check = end if end else max_date

        # Busca todos os avisos ativos (exceto o que estamos editando)
        query = "SELECT id, data_inicio, data_fim FROM avisos WHERE ativo = 1"
        params = []
        
        if exclude_id:
            query += " AND id != %s"
            params.append(exclude_id)
        
        cursor.execute(query, params)
        avisos_ativos = cursor.fetchall()
        cursor.close()
        conn.close()

        for row in avisos_ativos:
            # Intervalo do aviso já existente no banco
            db_start = row[1] if row[1] else min_date
            db_end = row[2] if row[2] else max_date
            
            # Lógica de Colisão: (InicioA < FimB) E (FimA > InicioB)
            # Se isso for verdade, os tempos se sobrepõem.
            if s_check < db_end and e_check > db_start:
                return True # CONFLITO DETECTADO!

        return False
    except Exception as e:
        print(f"Erro ao verificar conflito: {e}")
        return False

# --- ROTAS DE GERENCIAMENTO DE AVISOS ---

@app.route('/admin/avisos')
@login_required
def admin_avisos():
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    cursor.execute("SELECT * FROM avisos ORDER BY created_at DESC")
    avisos = cursor.fetchall()
    cursor.close()
    conn.close()
    
    # Passamos 'now' para o template poder bloquear avisos expirados visualmente
    return render_template('admin/avisos.html', avisos=avisos, now=datetime.now(), current_year=datetime.now().year)

# [IMPORTANTE] Esta é a rota que faltava para o botão EDITAR funcionar
@app.route('/admin/avisos/get/<int:aviso_id>', methods=['GET'])
@login_required
def get_aviso_dados(aviso_id):
    conn = get_db_connection()
    cursor = conn.cursor(pymysql.cursors.DictCursor)
    cursor.execute("SELECT * FROM avisos WHERE id = %s", (aviso_id,))
    aviso = cursor.fetchone()
    cursor.close()
    conn.close()
    
    if aviso:
        # Formata datas para o input HTML (YYYY-MM-DDTHH:MM)
        if aviso['data_inicio']:
            aviso['data_inicio'] = aviso['data_inicio'].strftime('%Y-%m-%dT%H:%M')
        if aviso['data_fim']:
            aviso['data_fim'] = aviso['data_fim'].strftime('%Y-%m-%dT%H:%M')
        return jsonify(aviso)
    return jsonify({'error': 'Aviso não encontrado'}), 404

@app.route('/admin/avisos/salvar', methods=['POST'])
@login_required
def salvar_aviso():
    try:
        aviso_id = request.form.get('id')
        titulo = request.form.get('titulo')
        mensagem = request.form.get('mensagem')
        tipo = request.form.get('tipo', 'info')
        inicio_str = request.form.get('data_inicio')
        fim_str = request.form.get('data_fim')
        
        # Conversão de Strings para Datetime
        inicio = datetime.strptime(inicio_str, '%Y-%m-%dT%H:%M') if inicio_str else None
        fim = datetime.strptime(fim_str, '%Y-%m-%dT%H:%M') if fim_str else None
        agora = datetime.now()

        # 1. Validação: Data Fim não pode ser menor que Início
        if inicio and fim and fim <= inicio:
            flash('A data de fim deve ser posterior à data de início.', 'danger')
            return redirect(url_for('admin_avisos'))

        # 2. Validação Retroativa: Não criar aviso que JÁ acabou
        if fim and fim < agora:
             flash('Não é possível criar um aviso que já expirou (Data Fim no passado).', 'danger')
             return redirect(url_for('admin_avisos'))

        # 3. Verifica Conflito (Tentamos salvar como ATIVO por padrão)
        ativo = 1
        
        # Se houver conflito de horário, salvamos como INATIVO forçadamente
        if check_conflict(inicio, fim, exclude_id=aviso_id):
            ativo = 0
            flash('Aviso salvo, mas mantido INATIVO pois o horário conflita com outro aviso.', 'warning')
        else:
            flash('Aviso salvo e programado com sucesso!', 'success')

        conn = get_db_connection()
        cursor = conn.cursor()
        
        if aviso_id:
            # Edição
            cursor.execute("""
                UPDATE avisos 
                SET titulo=%s, mensagem=%s, tipo=%s, data_inicio=%s, data_fim=%s, ativo=%s 
                WHERE id=%s
            """, (titulo, mensagem, tipo, inicio, fim, ativo, aviso_id))
        else:
            # Novo Cadastro
            cursor.execute("""
                INSERT INTO avisos (titulo, mensagem, tipo, data_inicio, data_fim, ativo)
                VALUES (%s, %s, %s, %s, %s, %s)
            """, (titulo, mensagem, tipo, inicio, fim, ativo))
        
        conn.commit()
        cursor.close()
        conn.close()
        
    except Exception as e:
        print(f"Erro ao salvar aviso: {e}")
        flash('Erro interno ao processar aviso.', 'danger')
        
    return redirect(url_for('admin_avisos'))

@app.route('/admin/avisos/toggle/<int:aviso_id>', methods=['POST'])
@login_required
def toggle_aviso(aviso_id):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        
        # Pega dados do aviso
        cursor.execute("SELECT ativo, data_inicio, data_fim FROM avisos WHERE id = %s", (aviso_id,))
        row = cursor.fetchone()
        
        if not row:
            return jsonify({'success': False, 'error': 'Aviso não encontrado'})
            
        estado_atual = row[0]
        start = row[1]
        end = row[2]
        novo_estado = 0 if estado_atual else 1
        
        # Se for ATIVAR (1), faz validações
        if novo_estado == 1:
            # 1. Validade
            if end and end < datetime.now():
                return jsonify({'success': False, 'error': 'Não é possível ativar um aviso expirado.'})

            # 2. Conflito
            if check_conflict(start, end, exclude_id=aviso_id):
                return jsonify({'success': False, 'error': 'Conflito! Já existe um aviso ativo neste período.'})
        
        # Atualiza status (agora permite múltiplos ativos se não conflitarem)
        cursor.execute("UPDATE avisos SET ativo = %s WHERE id = %s", (novo_estado, aviso_id))
        
        conn.commit()
        cursor.close()
        conn.close()
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/admin/avisos/delete/<int:aviso_id>', methods=['POST'])
@login_required
def delete_aviso(aviso_id):
    try:
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("DELETE FROM avisos WHERE id = %s", (aviso_id,))
        conn.commit()
        cursor.close()
        conn.close()
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

@app.route('/admin/gerar_relatorio', methods=['POST'])
@login_required
def admin_gerar_relatorio():
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

    cursor.close()
    conn.close()

    # CORREÇÃO AQUI: Formatando apenas como DD/MM/AAAA
    data = relatorio['created_at']
    # Se por acaso o banco retornar string, converte antes
    if isinstance(data, str):
        try:
            data = datetime.strptime(data, '%Y-%m-%d %H:%M:%S')
        except:
            pass # Mantém como está se falhar
            
    if hasattr(data, 'strftime'):
        data_criacao = data.strftime('%d/%m/%Y')
    else:
        # Fallback se não for objeto datetime
        data_criacao = str(data).split()[0] 
        # Tenta converter YYYY-MM-DD para DD/MM/YYYY manualmente se necessário
        if '-' in data_criacao:
            try:
                ano, mes, dia = data_criacao.split('-')
                data_criacao = f"{dia}/{mes}/{ano}"
            except:
                pass

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

# --- ROTA DE TOGGLE ATUALIZADA ---
@app.route('/admin/toggle_whatsapp', methods=['POST'])
@login_required
def toggle_whatsapp():
    try:
        data = request.get_json()
        
        conn = get_db_connection()
        cursor = conn.cursor()

        # Se enviou 'modo_automatico', atualiza essa chave
        if 'modo_automatico' in data:
            valor = '1' if data['modo_automatico'] else '0'
            cursor.execute("""
                INSERT INTO configuracoes (chave, valor) VALUES ('modo_automatico', %s)
                ON DUPLICATE KEY UPDATE valor = %s
            """, (valor, valor))
            
        # Se enviou 'ativo' (o botão manual), atualiza essa chave
        if 'ativo' in data:
            valor = '1' if data['ativo'] else '0'
            cursor.execute("""
                INSERT INTO configuracoes (chave, valor) VALUES ('whatsapp_ativo', %s)
                ON DUPLICATE KEY UPDATE valor = %s
            """, (valor, valor))

        conn.commit()
        cursor.close()
        conn.close()
        
        # Retorna o status calculado atual para atualizar a interface
        status_real = is_horario_comercial() if data.get('modo_automatico') else data.get('ativo')
        
        return jsonify({'success': True, 'status_real': status_real})
    except Exception as e:
        print(f"Erro ao alterar config: {e}")
        return jsonify({'success': False, 'error': str(e)})

# --- NOVA ROTA: SALVAR HORÁRIO ---
@app.route('/admin/save_schedule', methods=['POST'])
@login_required
def save_schedule():
    try:
        data = request.get_json() 
        # data agora inclui 'weekdays', 'saturday', 'sunday' E 'feriados'
        
        json_str = json.dumps(data)
        
        conn = get_db_connection()
        cursor = conn.cursor()
        cursor.execute("""
            INSERT INTO configuracoes (chave, valor) VALUES ('horario_atendimento', %s)
            ON DUPLICATE KEY UPDATE valor = %s
        """, (json_str, json_str))
        conn.commit()
        cursor.close()
        conn.close()
        
        return jsonify({'success': True})
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})

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

# --- Substitua a função api_consultar_cpf inteira por esta ---

@app.route('/api/consultar_cpf', methods=['POST'])
def api_consultar_cpf():
    try:
        # Debug: Mostra o que chegou
        data = request.get_json()
        print(f"DEBUG API: Dados recebidos: {data}")
        
        cpf_raw = data.get('cpf', '')
        
        # Limpeza Python: Remove tudo que não for número do input
        cpf_limpo = re.sub(r'\D', '', str(cpf_raw))
        print(f"DEBUG API: CPF limpo para busca: '{cpf_limpo}'")
        
        if not cpf_limpo:
            print("DEBUG API: CPF vazio após limpeza.")
            return jsonify({'found': False, 'reason': 'CPF inválido'})

        conn = get_db_connection()
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        
        # Query Corrigida: Removido ', cidade' do SELECT
        query = """
            SELECT nome, rua, numero, bairro, cpf 
            FROM cadastros 
            WHERE REGEXP_REPLACE(cpf, '[^0-9]', '') = %s
            LIMIT 1
        """
        
        cursor.execute(query, (cpf_limpo,))
        cliente = cursor.fetchone()
        
        cursor.close()
        conn.close()
        
        if cliente:
            print(f"DEBUG API: Cliente encontrado! Nome: {cliente['nome']}")
            
            # Formata endereço sem cidade (já que não tem no banco)
            rua = cliente['rua'] or 'Rua não cadastrada'
            num = cliente['numero'] or 'S/N'
            bairro = cliente['bairro'] or ''
            
            # Monta endereço: Rua X, 123 - Bairro
            endereco = f"{rua}, {num} - {bairro}"
            
            return jsonify({
                'found': True,
                'nome': cliente['nome'],
                'endereco': endereco
            })
        else:
            print(f"DEBUG API: NENHUM cliente encontrado para o CPF {cpf_limpo}")
            return jsonify({'found': False, 'reason': 'Não encontrado no banco'})
            
    except Exception as e:
        print(f"ERRO CRÍTICO NA API: {e}")
        import traceback
        traceback.print_exc()
        return jsonify({'found': False, 'error': str(e)})

@app.route('/api/check_status', methods=['POST'])
def api_check_status():
    data = request.get_json()
    cpf_raw = data.get('cpf', '')
    # Limpa CPF para comparar apenas números
    cpf_limpo = re.sub(r'\D', '', cpf_raw)

    if not cpf_limpo:
        return jsonify({'found': False})

    try:
        conn = get_db_connection()
        cursor = conn.cursor(pymysql.cursors.DictCursor)
        
        # Busca status e data de instalação
        cursor.execute("""
            SELECT status_instalacao, data_instalacao 
            FROM cadastros 
            WHERE REPLACE(REPLACE(cpf, '.', ''), '-', '') = %s
            LIMIT 1
        """, (cpf_limpo,))
        
        result = cursor.fetchone()
        cursor.close()
        conn.close()

        if result:
            return jsonify({
                'found': True,
                'status': result['status_instalacao'],
                'data': result['data_instalacao']
            })
        else:
            return jsonify({'found': False})

    except Exception as e:
        print(f"Erro ao verificar status: {e}")
        return jsonify({'found': False, 'error': str(e)})


if __name__ == '__main__':
    if wait_for_db():
        init_db()
        app.run(host='0.0.0.0', port=5000, debug=False)
    else:
        raise RuntimeError("Não foi possível iniciar o aplicativo: falha na conexão com o banco de dados.")