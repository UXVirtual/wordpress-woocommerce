# Wordpress WooCommerce

This is a Dockerized Wordpress + WooCommerce website, configured with MariaDB. It is divided into 3x containers based on the `bitnami/nginx` Docker image for easier scaling and deployment. In a local dev environment the wp-uploads and specific plugins folders are exposed to the host:

* `wp-content/plugins/wc-extension`
* `wp-content/uploads`

This allows these to be versioned for convenience, however it is expected that a seeded database backup will also be stored for the local dev environments.

1. `wordpress_nginx` (php8.1-fpm) - This runs the Wordpress app
2. `wordpress_app` (nginx) - This proxies the Wordpress app, allowing for SSL configuration. In production should be used with cloudflare or other service to better handle traffic when the website is in a non-logged in state.
3. `wordpress_db` (MariaDB) - Database fork of MySQL. Is open source and has various performance improvements over MySQL, with better thread pooling,  caching and system wide versioned tables. Is a drop-in replacement for MySQL, compatible with Wordpress.

The `bitnami/nginx` container is quite sparse by design, only having the bare minimum binaries to run nginx, plus having the Wordpress app and web service running on separate containers further decreases the blast radius if rogue extensions get access. For maximum security, all 3x containers should be deployed in a single AWS VPC isolated from other applications with API gateway restricting traffic to port 443 and 80.

## Developers

## Installation

1. Copy `.env.example` to `.env`, with your desired database credentials.

2. Run `id -u` to `id -g` to find the respective UID and GID values that match your host OS's user. This is needed so that `wp-uploads` content is readable by the `wordpress_app` Docker container.

3. Run `docker-compose up -d --build` to build and start the docker containers.

### Docker Changes

In this stack, databases will persist between redeployments of the containers.

* If adjusting `wordpress.conf`, the `Dockerfile` or `docker-compose.yml`, run `docker-compose up -d` to redeploy the containers with the new config.

* If adjusting the `Dockerfile`, `.env` or `docker-compose.yml`, run `docker-compose up -d --build` to rebuild the images and redeploy the containers with the new config.

* In some cases, you may need to manually delete the containers and redeploy them.

### PHP Debugging

This project is configured to allow PHP debugging inside the `wordpress_app` container that runs [PHP-FPM](https://php-fpm.org/).

1. Install the [PHP Debug extension](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug) by [xdebug.org](https://xdebug.org/) from the VS Code Marketplace.

2. Set a Breakpoint: Open a PHP file in your wordpress directory, for example, wordpress/wp-load.php. Click in the gutter to the left of a line number to set a red dot, which is a breakpoint.

3. Start the Debugger: Go back to the "Run and Debug" panel in VS Code. Make sure "Listen for Xdebug" is selected in the dropdown, and click the green play button to start it. The VS Code status bar will turn orange, indicating it's listening for a connection.

4. You can also view all container logs in a unified view by selecting `wordpress-woocommerce`, viewing the logs for the wordpress_app specific container, or running the following in the Windows CLI to tail the logs from the container:

```
docker logs -f wordpress_app
```

### Logging

The bitnami nginx container is configured to not store logs on disk. In production these would be typically exposed to a logging service on whatever cloud hosting provider the container is deployed to (e.g. AWS CloudWatch).

As a workaround, logs are redirected to `stdout`, so will be available to view in the Docker console or via the following command:

```
docker logs wordpress_nginx
```

### DevOps & Deployment

Both of these options could be deployed via IaC (Terraform / CloudFormation) templates added to this repository.

* When deploying as part of a CI/CD pipeline, run `docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build` in the p

* Amazon RDS - This is a drop-in replacement for MariaDB and will work with Wordpress. It automatically handles backups / patching / scaling and supports high-availability.

* Amazon ECS - If the containers are deployed via AWS using Amazon Elastic Container Service (ECS) and an EFS file system, this requires a low amount of config and effectively a drop-in replacement this may have some file system latency and more expensive. This can also be an approach that would work with images deployed via AWS Elastic Beanstalk. If deployed via AWS Fargate, some additional ECS Task Definition config is needed to define how many containers to spin up from Amazon Elastic Container Registry (AWS's private Docker image repo), scaling criteria and to and register the containers with an Application Load Balancer (ALB).

* Amazon S3 - With additional config and the WP Offload Media plugin, all uploads in the wp-uploads folder can reside on Amazon S3 which is very cost-effective. This overrides the default behavior when files are uploaded to send to Amazon S3. If used in conjunction with Amazon Cloudfront CDN URLs it can be very durable and scalable.

