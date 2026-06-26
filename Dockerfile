# Dockerfile para rodar a aplicação PHP no Apache
# Usamos a imagem oficial do PHP 8.3 com Apache integrado
FROM php:8.3-apache

# Habilita o mod_rewrite do Apache para suporte a reescrita de URLs se necessário
RUN a2enmod rewrite headers && echo "ServerName mcdmarketprime.com" > /etc/apache2/conf-available/servername.conf && a2enconf servername

# Copia todos os arquivos do projeto para o diretório público do servidor Apache
COPY . /var/www/html/

# Ajusta as permissões de todos os arquivos para o usuário padrão do Apache (www-data)
# Isso garante que a aplicação possa criar e gravar na pasta 'cache/' sem erros de permissão
RUN chown -R www-data:www-data /var/www/html

# Expõe a porta padrão do Apache (80)
EXPOSE 80
