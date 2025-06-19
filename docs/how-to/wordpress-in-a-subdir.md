# Using a subdirectory for your blog

For WebFinger to function properly, it needs to be mapped to the root directory of your blog’s URL.

## Apache

Add the following lines to the `.htaccess` file located in your site's root directory:

	RedirectMatch "^\/\.well-known/(webfinger|nodeinfo)(.*)$" /blog/.well-known/$1$2

…where `blog` is the path to the subdirectory where your blog is installed.

## Nginx

Add the following lines to your `site.conf` file in the `sites-available` directory:

	location ~* /.well-known {
		allow all;
		try_files $uri $uri/ /blog/?$args;
	}

Where `blog` is the path to the subdirectory where your blog is installed.

If your blog is installed in a subdirectory but you’ve set a different [wp_siteurl](https://wordpress.org/documentation/article/giving-wordpress-its-own-directory/), you don’t need the redirect — index.php will handle it automatically.
