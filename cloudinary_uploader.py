# -*- coding: utf-8 -*-
"""
cloudinary_uploader.py: Script utilitário em Python para converter imagens locais
para o formato WebP (usando Pillow) e enviar para o Cloudinary via API REST (usando requests).
Retorna o resultado em formato JSON para ser facilmente consumido por scripts PHP.
"""

import sys
import os
import time
import hashlib
import json
import requests
from PIL import Image

def load_env(env_path):
    """
    Carrega variáveis de ambiente a partir de um arquivo .env local.
    Respeita a regra global de incluir comentários detalhados em todas as partes.
    """
    env_vars = {}
    if os.path.exists(env_path):
        # Abre o arquivo .env com codificação utf-8 para leitura segura
        with open(env_path, "r", encoding="utf-8") as f:
            for line in f:
                line = line.strip()
                # Descarta linhas em branco e comentários do arquivo
                if not line or line.startswith("#"):
                    continue
                # Divide a string no primeiro caractere '='
                parts = line.split("=", 1)
                if len(parts) == 2:
                    key = parts[0].strip()
                    val = parts[1].strip().strip("\"'")
                    env_vars[key] = val
    return env_vars

# Determina o caminho para o arquivo .env no mesmo diretório deste script
ENV_PATH = os.path.join(os.path.dirname(__file__), ".env")
env_config = load_env(ENV_PATH)

# Carrega as credenciais dinamicamente do .env ou usa valores estáticos de fallback
CLOUD_NAME = env_config.get("CLOUDINARY_CLOUD_NAME", "dpidtimit")
API_KEY = env_config.get("CLOUDINARY_API_KEY", "565664779947625")
API_SECRET = env_config.get("CLOUDINARY_API_SECRET", "MWOiHTXk98oA8WeKHm3NA68-5_I")

def convert_and_upload(source_path):
    """
    Converte a imagem de origem para WebP e realiza o upload assinado para o Cloudinary.
    """
    if not os.path.exists(source_path):
        return {"error": f"O arquivo de origem nao existe: {source_path}"}
        
    # Gera um caminho temporário para a imagem WebP convertida
    temp_webp = source_path + ".converted.webp"
    
    try:
        # Abre a imagem de origem e salva no formato WebP
        with Image.open(source_path) as img:
            img.save(temp_webp, "webp")
    except Exception as e:
        return {"error": f"Falha ao converter a imagem para WebP: {str(e)}"}
        
    # Obtém o timestamp do Unix para expiração da requisição
    timestamp = int(time.time())
    folder = "techdeal"
    
    # Constrói a assinatura baseada nos parâmetros em ordem alfabética
    # Formato: parameter1=value1&parameter2=value2<api_secret>
    string_to_sign = f"folder={folder}&timestamp={timestamp}{API_SECRET}"
    signature = hashlib.sha1(string_to_sign.encode("utf-8")).hexdigest()
    
    # URL da API REST do Cloudinary para uploads
    url = f"https://api.cloudinary.com/v1_1/{CLOUD_NAME}/image/upload"
    
    try:
        # Envia a requisição multipart/form-data com o arquivo convertido
        with open(temp_webp, "rb") as f:
            files = {"file": f}
            data = {
                "api_key": API_KEY,
                "timestamp": timestamp,
                "folder": folder,
                "signature": signature
            }
            response = requests.post(url, files=files, data=data, timeout=60)
            
        # Remove a imagem WebP temporária após a requisição
        if os.path.exists(temp_webp):
            os.remove(temp_webp)
            
        # Verifica se o Cloudinary retornou sucesso (HTTP 200)
        if response.status_code == 200:
            res_json = response.json()
            return {"secure_url": res_json.get("secure_url")}
        else:
            return {"error": f"Erro Cloudinary (HTTP {response.status_code}): {response.text}"}
            
    except Exception as e:
        # Garante a limpeza do arquivo temporário se houver alguma exceção
        if os.path.exists(temp_webp):
            os.remove(temp_webp)
        return {"error": f"Erro de conexao com Cloudinary: {str(e)}"}

if __name__ == "__main__":
    # Verifica se o caminho da imagem de origem foi passado por argumento
    if len(sys.argv) < 2:
        print(json.dumps({"error": "Caminho do arquivo nao fornecido por argumento"}))
        sys.exit(1)
        
    source = sys.argv[1]
    result = convert_and_upload(source)
    # Exibe o resultado como JSON na saída padrão (stdout) para leitura pelo PHP
    print(json.dumps(result))
