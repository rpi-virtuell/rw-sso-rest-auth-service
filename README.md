# RW-SSO-REST-AUTH-SERVICE

*A WordPress Plugin which provides REST Routes that are 
used by the [RW-SSO-REST-AUTH-CLIENT](https://github.com/rpi-virtuell/rw-sso-rest-auth-client) Plugin to provide 
**S**ingle **S**ign **O**n (SSO) capability of a WordPress network*

## Installation

### 1. Whitelist remote servers
In order to allow remote servers to send REST calls to 
the SSO service providing server the user
needs to whitelist these servers.

*Note: If no whitelist is provided all Servers with the same IP Address as the host
server are whitelisted!*

This is done by defining a Constant in the wp-config.php file
of your Service Providing Server. *This is done this way
to prevent network vulnerability.*

**!!!Remember to supply and array like in the example!!!**

>define('ALLOWED_SSO_CLIENTS', array('127.0.0.1'));

This an example how you would whitelist a Server
in this case with the IP-Address 127.0.0.1 any number 
of Servers may be provided

### Download
GitHub: [RW-SSO-REST-AUTH-CLIENT](https://github.com/rpi-virtuell/rw-sso-rest-auth-client)
