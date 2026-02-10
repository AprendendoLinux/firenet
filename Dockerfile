# Dockerfile
FROM python:3.11-slim

ARG APP_VERSION=dev-local
ENV APP_VERSION=${APP_VERSION}
WORKDIR /app

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

EXPOSE 5000

CMD ["python", "app.py"]