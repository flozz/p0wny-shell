FROM php:latest

RUN mkdir /app
COPY shell.php /app/shell.php

WORKDIR /app
EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080"]
