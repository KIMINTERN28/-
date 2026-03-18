FROM php:8.2-apache

# 将当前目录下的所有文件复制到服务器的网页根目录
COPY . /var/www/html/

# 开启 Apache 的重写模块（如果你以后用到路由跳转会很有用）
RUN a2enmod rewrite

# 暴露 80 端口
EXPOSE 80