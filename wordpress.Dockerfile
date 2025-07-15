# Start from a specific WordPress version with PHP 8.1 FPM.
# Pinning the version ensures consistent development environments.
FROM wordpress:6.8.1-php8.1-fpm

# Install dependencies needed for Xdebug, zip, and curl/wget in a single layer.
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

# Download and install all specified plugins in a single RUN command to reduce image layers.
RUN curl -L -o /tmp/woocommerce.zip https://downloads.wordpress.org/plugin/woocommerce.10.0.2.zip && \
    unzip /tmp/woocommerce.zip -d /usr/src/wordpress/wp-content/plugins/ && \
    \
    curl -L -o /tmp/woocommerce-payments.zip https://downloads.wordpress.org/plugin/woocommerce-payments.9.6.0.zip && \
    unzip /tmp/woocommerce-payments.zip -d /usr/src/wordpress/wp-content/plugins/ && \
    \
    curl -L -o /tmp/woocommerce-paypal-payments.zip https://downloads.wordpress.org/plugin/woocommerce-paypal-payments.3.0.7.zip && \
    unzip /tmp/woocommerce-paypal-payments.zip -d /usr/src/wordpress/wp-content/plugins/ && \
    \
    # Clean up all temporary zip files at the end.
    rm /tmp/*.zip

# Set the correct ownership for all files in the plugins directory that we added.
# This is safer than relying on the entrypoint for files you add manually.
RUN chown -R www-data:www-data /usr/src/wordpress/wp-content/plugins/
