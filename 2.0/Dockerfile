# Dockerfile
FROM python:3.11-slim

WORKDIR /app

# Instala apenas Flask
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copia o projeto
COPY . .

# Expõe a porta
EXPOSE 5000

# Roda com Python puro (Flask nativo)
CMD ["python", "app.py"]