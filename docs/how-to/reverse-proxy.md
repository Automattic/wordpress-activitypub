# Handling reverse proxy setups with Apache

If you are using a reverse proxy with Apache to serve your site, you may find that followers are unable to follow your blog. This happens because the proxy rewrites the `Host` header to your server’s internal DNS name, which the plugin then uses to sign replies. However, remote servers expect replies to be signed with your public DNS name. To resolve this, you need to use the `ProxyPreserveHost On` directive to ensure that the external host name is passed through to the backend server.

If you are using SSL between the reverse proxy and the internal host, you may also need to set `SSLProxyCheckPeerName off` if the internal host does not present the correct SSL certificate. Be aware that this can introduce a security risk in some environments.

## Example

```apache
<VirtualHost *:443>
    ServerName example.com
    ProxyPreserveHost On
    SSLProxyCheckPeerName off
    ProxyPass / http://localhost:8080/
    ProxyPassReverse / http://localhost:8080/
</VirtualHost>
```
