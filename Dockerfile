FROM debian:buster-slim

RUN apt-get update \
    && apt-get install -y locales apache2 php7.3 \
    && rm -rf /var/lib/apt/lists/* \
    && localedef -i en_US -c -f UTF-8 -A /usr/share/locale/locale.alias en_US.UTF-8 \
    && chown -R www-data:www-data /var/www/html

ENV LANG en_US.utf8
ENV APACHE_RUN_DIR /var/run/apache2
ENV APACHE_RUN_USER www-data
ENV APACHE_RUN_GROUP www-data
ENV APACHE_PID_FILE /var/run/apache2/apache2.pid
ENV APACHE_LOG_DIR /var/log/apache2

COPY shell.php /var/www/html/shell.php

EXPOSE 80

CMD ["/usr/sbin/apache2", "-DFOREGROUND"]
