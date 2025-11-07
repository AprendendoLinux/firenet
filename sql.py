# sql.py
import os
import pymysql
import time
from werkzeug.security import generate_password_hash

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

# Função para aguardar o banco de dados estar disponível
def wait_for_db(max_retries=30, retry_interval=5):
    retries = 0
    while retries < max_retries:
        try:
            conn = pymysql.connect(
                host=DB_HOST,
                user=DB_USER,
                password=DB_PASSWORD,
                charset='utf8mb4'
            )
            cursor = conn.cursor()
            cursor.execute(f"CREATE DATABASE IF NOT EXISTS {DB_NAME}")
            conn.commit()
            cursor.close()
            conn.close()
            print("Conexão com o banco de dados estabelecida com sucesso.")
            return True
        except pymysql.err.OperationalError as e:
            retries += 1
            print(f"Erro ao conectar ao banco de dados: {e}. Tentativa {retries}/{max_retries}. Aguardando {retry_interval} segundos...")
            time.sleep(retry_interval)
    print("Falha ao conectar ao banco de dados após o máximo de tentativas.")
    return False

# Check e create tabelas ao iniciar
def init_db():
    conn = get_db_connection()
    cursor = conn.cursor()

    # Verifica se tabelas existem
    cursor.execute("SHOW TABLES")
    existing_tables = [table[0] for table in cursor.fetchall()]

    if 'cadastros' not in existing_tables:
        cursor.execute("""
            CREATE TABLE `cadastros` (
            `id` int NOT NULL,
            `nome` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `cpf` varchar(14) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `rg` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `data_nascimento` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `telefone` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `whatsapp` enum('Sim','Não') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `email` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `cep` varchar(9) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `rua` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `numero` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `complemento` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `ponto_referencia` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `bairro` varchar(100) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `plano` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `vencimento` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `nome_rede` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `senha_rede` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `lgpd` enum('Sim') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
            `data_cadastro` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `data_instalacao` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `turno_instalacao` enum('Manhã','Tarde','Horário Comercial') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
            `status_instalacao` enum('N/D','Programada','Concluída','Não Realizada') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,  # Adicionado 'N/D'
            `observacoes` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        # Adiciona a chave primária
        cursor.execute("ALTER TABLE `cadastros` ADD PRIMARY KEY (`id`);")
        # Adiciona AUTO_INCREMENT
        cursor.execute("ALTER TABLE `cadastros` MODIFY `id` int NOT NULL AUTO_INCREMENT;")

    # Para tabelas existentes: Atualiza o enum de status_instalacao para incluir 'N/D' (se não existir)
    cursor.execute("SHOW COLUMNS FROM cadastros LIKE 'status_instalacao'")
    column_info = cursor.fetchone()
    if column_info and "'N/D'" not in column_info[1]:  # Verifica se 'N/D' já está no enum
        cursor.execute("""
            ALTER TABLE cadastros MODIFY status_instalacao 
            ENUM('N/D', 'Programada', 'Concluída', 'Não Realizada') 
            CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL
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
                email VARCHAR(255) UNIQUE NOT NULL,
                telegram_id BIGINT DEFAULT NULL
            )
        """)
        # Após criar a tabela, criar admin
        hashed_password = generate_password_hash('admin')
        cursor.execute("""
            INSERT INTO usuarios (username, password, email, telegram_id)
            VALUES (%s, %s, %s, %s)
        """, ('admin', hashed_password, 'admin@dominio.com', 12345678910))
        conn.commit()
        print("Usuário admin criado com sucesso.")

    # Para tabelas existentes: Adiciona a coluna telegram_id se não existir
    cursor.execute("SHOW COLUMNS FROM usuarios LIKE 'telegram_id'")
    if not cursor.fetchone():
        cursor.execute("""
            ALTER TABLE usuarios ADD COLUMN telegram_id BIGINT DEFAULT NULL
        """)

    if 'relatorios' not in existing_tables:
        cursor.execute("""
            CREATE TABLE `relatorios` (
                `id` int NOT NULL,
                `identificador` varchar(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                `data_vencimento` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                `clientes_ids` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        """)
        cursor.execute("ALTER TABLE `relatorios` ADD PRIMARY KEY (`id`);")
        cursor.execute("ALTER TABLE `relatorios` MODIFY `id` int NOT NULL AUTO_INCREMENT;")

    # Novo: Para tabelas existentes, drop unique se existir e ajusta tamanho de identificador para 4
    if 'relatorios' in existing_tables:
        # Drop unique index se existir
        cursor.execute("SHOW INDEX FROM relatorios WHERE Key_name = 'identificador'")
        if cursor.fetchone():
            cursor.execute("ALTER TABLE relatorios DROP INDEX identificador")
            print("Unique key em 'identificador' removida.")
        
        # Verifica e altera o tamanho da coluna se necessário
        cursor.execute("SHOW COLUMNS FROM relatorios LIKE 'identificador'")
        column_info = cursor.fetchone()
        if column_info and 'varchar(10)' in column_info[1]:  # Se for 10, altera para 4
            cursor.execute("""
                ALTER TABLE relatorios MODIFY identificador 
                VARCHAR(4) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL
            """)
            print("Coluna 'identificador' alterada para VARCHAR(4).")

    if 'cobertura' not in existing_tables:
        cursor.execute("""
            CREATE TABLE cobertura (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tipo_logradouro VARCHAR(255) NOT NULL,
                nome_logradouro VARCHAR(255) NOT NULL,
                bairro VARCHAR(255) NOT NULL,
                cidade VARCHAR(255) NOT NULL,
                estado VARCHAR(255) NOT NULL,
                pais VARCHAR(255) NOT NULL
            )
        """)
        # Inserir endereços padrão
        cursor.execute("""
            INSERT INTO cobertura (tipo_logradouro, nome_logradouro, bairro, cidade, estado, pais)
            VALUES ('Rua', 'Mateus Silva', 'Inhaúma', 'Rio de Janeiro', 'RJ', 'Brasil')
        """)
        cursor.execute("""
            INSERT INTO cobertura (tipo_logradouro, nome_logradouro, bairro, cidade, estado, pais)
            VALUES ('Rua', 'Soares Meireles', 'Pilares', 'Rio de Janeiro', 'RJ', 'Brasil')
        """)

    conn.commit()
    cursor.close()
    conn.close()
    print("Tabelas verificadas e inicializadas com sucesso.")