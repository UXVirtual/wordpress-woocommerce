# Start from a specific WordPress version with PHP 8.1 FPM.
FROM wordpress:6.8.1-php8.1-fpm

# Set up arguments for user and group IDs with default values.
# This makes the Dockerfile more resilient.
ARG UID=1000
ARG GID=1000

# Setup user and group IDs for www-data.
RUN groupmod -o -g ${GID} www-data && \
    usermod -o -u ${UID} -g www-data www-data

# Install dependencies needed for Xdebug, zip, and curl/wget.
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Install the official zip PHP extension.
RUN docker-php-ext-install zip

# Install Xdebug using PECL for runtime debugging.
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug

# Download and install all specified plugins. Allows version pinning for better management and a cleaner
# DevOps management of the WordPress instance.
RUN curl -L -o /tmp/woocommerce.zip https://downloads.wordpress.org/plugin/woocommerce.10.0.2.zip && \
    unzip /tmp/woocommerce.zip -d /usr/src/wordpress/wp-content/plugins/ && \
    curl -L -o /tmp/woocommerce-payments.zip https://downloads.wordpress.org/plugin/woocommerce-payments.9.6.0.zip && \
    unzip /tmp/woocommerce-payments.zip -d /usr/src/wordpress/wp-content/plugins/ && \
    curl -L -o /tmp/woocommerce-paypal-payments.zip https://downloads.wordpress.org/plugin/woocommerce-paypal-payments.3.0.7.zip && \
    unzip /tmp/woocommerce-paypal-payments.zip -d /usr/src/wordpress/wp-content/plugins/ && \
    rm /tmp/*.zip

# Set the correct ownership for all files in the plugins directory.
RUN chown -R www-data:www-data /usr/src/wordpress/wp-content/plugins/
