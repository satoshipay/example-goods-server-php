# Example SatoshiPay goods server (PHP)

This is an example implementation of a SatoshiPay [HTTP endpoint](http://docs.satoshipay.io/api/#http-endpoints) written in PHP.

## Features

- Support for range requests (partial HTTP content), which is required to skip in video files
- Automatic matching of request URLs to directories

## Configuration

### Environment variables

Configure the server using these environment variables:

- **`DEBUG`**: Enables PHP debug output if set to `1`
- **`ERROR_LOG`**: Allows a custom PHP error log, example: `/usr/log/php/errors.log`
- **`PATH_PREFIX`**: Set directory that contains your goods, example: `/usr/share/satoshipay-files/` (default: `../files/`)

### .htaccess

Using these rewrite rules will allow you to serve files using URLs like `http://example.org/example.html`:

    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ /index.php?file=$1 [QSA,L]

## Adding files

Study the example in the `files` directory of this repository or follow these steps:

1. Create a subdirectory in your files directory (set via PATH_PREFIX environment variable), name it the same as the file you want to serve.
2. Place the file into the subdirectory.
3. Add a file called `metadata.ini` with the following content to the subdirectory:

        secret = <your secret>
        content_type = text/html

    `secret` needs to match the secret registered with the [Digital Goods API](http://docs.satoshipay.io/api/#digital-goods-api).

    `content_type` is optional and should be detected correctly if your webserver is configured properly. This needs to match the `data-sp-type` attibute of the [HTML tag](http://docs.satoshipay.io/api/#html-tags) that embeds your digital good into a page.

Use URLs like `http://example.org/?file=example.html` when registering and accessing your digital goods. See `.htaccess` section for rewrite options.
