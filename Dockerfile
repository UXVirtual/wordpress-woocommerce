# Use the latest Bitnami NGINX image as the base
FROM bitnami/nginx:latest

# Switch to the root user to have permissions for configuration
USER root

# Modify the main NGINX configuration to log to stdout/stderr.
# This is the standard practice for containers and prevents startup errors
# if the log directory doesn't exist. We target the main config file.
RUN sed -i 's#access_log .*;#access_log /dev/stdout;#g' /opt/bitnami/nginx/conf/nginx.conf && \
    sed -i 's#error_log .*;#error_log /dev/stderr;#g' /opt/bitnami/nginx/conf/nginx.conf

# Remove the default server block configuration that comes with the image.
# Using -f to avoid errors if the file doesn't exist.
RUN rm -f /opt/bitnami/nginx/conf/server_blocks/default.conf

# Copy the custom WordPress NGINX configuration into the container.
# This file should be in the same directory as the Dockerfile.
COPY wordpress.conf /opt/bitnami/nginx/conf/server_blocks/wordpress.conf

# The bitnami/nginx image will automatically include configurations
# from the server_blocks directory.
# The base image's entrypoint will start NGINX.
