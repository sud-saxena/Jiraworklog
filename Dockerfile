FROM php:8.1-apache

# Increase header limits (fix Bad Request issue)
RUN echo "LimitRequestFieldSize 65536" >> /etc/apache2/apache2.conf \
 && echo "LimitRequestLine 65536" >> /etc/apache2/apache2.conf \
 && echo "HttpProtocolOptions Unsafe" >> /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY . /var/www/html

COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]

EXPOSE 80