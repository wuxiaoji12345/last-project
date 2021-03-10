FROM docker.lingmou.ai:8001/web/nginx-php7.2-fpm:latest

# nginx Vhost
RUN rm /etc/nginx/sites-enabled/default
COPY ./docker/nginx-vhost /etc/nginx/sites-enabled/

# php custom config
COPY ./docker/php-conf.ini /etc/php/7.2/fpm/conf.d/
COPY ./docker/php-conf.ini /etc/php/7.2/cli/conf.d/
#COPY ./docker/cacert.pem /etc/ssl/certs/
# php pool 进程设置 具体值要根据内存大小配置
# pm.max_children 参考 占用最大内存/单个进程占用内存  4G/0.025G = 160
# pm.min_spare_servers 预估并发数
# pm.max_spare_servers 预估接近max_children时，超过 max_spare_servers 时，会关闭一些sleep状态的进程
RUN sed -i 's/pm.max_children.*/pm.max_children = 160/' /etc/php/7.2/fpm/pool.d/www.conf
RUN sed -i 's/pm.min_spare_servers.*/pm.min_spare_servers = 40/' /etc/php/7.2/fpm/pool.d/www.conf
RUN sed -i 's/pm.max_spare_servers.*/pm.max_spare_servers = 140/' /etc/php/7.2/fpm/pool.d/www.conf
RUN sed -i 's/pm.start_servers.*/pm.start_servers = 40/' /etc/php/7.2/fpm/pool.d/www.conf

# supervisord
COPY ./docker/supervisord.conf /etc/supervisor/conf.d/

# Application Code
RUN mkdir /application && mkdir -p /run/php
ADD --chown=www-data:www-data ./ /application
RUN chown www-data:www-data /application -Rf

RUN chmod 777 /application/env.sh
# RUN chown www-data:www-data /application -Rf
# RUN sed -i s@/archive.ubuntu.com/@/mirrors.aliyun.com/@g /etc/apt/sources.list
# RUN apt-get update
# RUN apt-get install  php7.2-xdebug
# RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /application

CMD ["/bin/bash", "-c", "bash /application/env.sh; sleep 1; /usr/bin/supervisord -c /etc/supervisor/supervisord.conf"]
