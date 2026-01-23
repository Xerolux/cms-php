vcl 4.1;

import std;
import directors;

backend default {
    .host = "xquantoria-backend";
    .port = "9000";
    .max_connections = 300;
    .connect_timeout = 5s;
    .first_byte_timeout = 90s;
    .between_bytes_timeout = 2s;
    .probe = {
        .url = "/api/v1/health";
        .timeout = 2s;
        .interval = 5s;
        .window = 5;
        .threshold = 3;
    }
}

backend frontend {
    .host = "xquantoria-frontend";
    .port = "80";
    .max_connections = 300;
    .connect_timeout = 5s;
    .first_byte_timeout = 90s;
    .between_bytes_timeout = 2s;
    .probe = {
        .url = "/";
        .timeout = 2s;
        .interval = 5s;
        .window = 5;
        .threshold = 3;
    }
}

acl purge_allowed {
    "localhost";
    "127.0.0.1";
    "::1";
    "172.16.0.0"/12;  # Docker network
    "10.0.0.0"/8;     # Private networks
}

sub vcl_init {
    # Backend director for round-robin
    new api_director = directors.round_robin();
    api_director.add_backend(default);

    new web_director = directors.round_robin();
    web_director.add_backend(frontend);
}

sub vcl_recv {
    # Set backend based on request path
    if (req.url ~ "^/api/") {
        set req.backend_hint = api_director.backend();
    } else {
        set req.backend_hint = web_director.backend();
    }

    # Handle PURGE requests
    if (req.method == "PURGE") {
        if (!client.ip ~ purge_allowed) {
            return (synth(405, "PURGE not allowed from this IP"));
        }
        return (purge);
    }

    # Handle BAN requests
    if (req.method == "BAN") {
        if (!client.ip ~ purge_allowed) {
            return (synth(405, "BAN not allowed from this IP"));
        }

        ban("req.url ~ " + req.http.X-Ban-Url);
        return (synth(200, "Banned"));
    }

    # Handle BAN requests for specific tags
    if (req.method == "BAN" && req.http.X-Cache-Tags) {
        if (!client.ip ~ purge_allowed) {
            return (synth(405, "BAN not allowed from this IP"));
        }

        ban("obj.http.X-Cache-Tags ~ " + req.http.X-Cache-Tags);
        return (synth(200, "Banned tags: " + req.http.X-Cache-Tags));
    }

    # Only cache GET and HEAD requests
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }

    # Don't cache authenticated requests
    if (req.http.Authorization || req.http.Cookie ~ "(user_session|auth_token)") {
        return (pass);
    }

    # Don't cache specific paths
    if (req.url ~ "^/admin" ||
        req.url ~ "^/api/v1/auth" ||
        req.url ~ "^/api/v1/user" ||
        req.url ~ "^/api/v1/search") {
        return (pass);
    }

    # Remove tracking query parameters from cache key
    set req.url = regsuball(req.url, "[?&](utm_source|utm_medium|utm_campaign|utm_content|utm_term|fbclid|gclid)=[^&]+", "");
    set req.url = regsuball(req.url, "[?&]$", "");

    # Normalize Accept-Encoding header
    if (req.http.Accept-Encoding) {
        if (req.url ~ "\.(jpg|jpeg|png|gif|gz|tgz|bz2|tbz|mp3|ogg|swf|flv)$") {
            unset req.http.Accept-Encoding;
        } elsif (req.http.Accept-Encoding ~ "gzip") {
            set req.http.Accept-Encoding = "gzip";
        } elsif (req.http.Accept-Encoding ~ "deflate") {
            set req.http.Accept-Encoding = "deflate";
        } else {
            unset req.http.Accept-Encoding;
        }
    }

    # Remove cookies for static assets
    if (req.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$") {
        unset req.http.Cookie;
        return (hash);
    }

    # Cache HTML pages with specific rules
    if (req.url ~ "\.html$" || req.url ~ "/$") {
        unset req.http.Cookie;
    }

    # Remove all cookies except for specific ones
    if (req.http.Cookie) {
        # Keep only necessary cookies
        set req.http.Cookie = ";" + req.http.Cookie;
        set req.http.Cookie = regsuball(req.http.Cookie, "; +", ";");
        set req.http.Cookie = regsuball(req.http.Cookie, ";(language|locale|theme|currency)=", "; \1=");
        set req.http.Cookie = regsuball(req.http.Cookie, ";[^ ][^;]*", "");
        set req.http.Cookie = regsuball(req.http.Cookie, "^[; ]+|[; ]+$", "");

        if (req.http.Cookie == "") {
            unset req.http.Cookie;
        }
    }

    # Grace mode - serve stale objects if backend is slow
    set req.http.X-Varnish-Grace = "true";

    return (hash);
}

sub vcl_hash {
    hash_data(req.url);

    if (req.http.Host) {
        hash_data(req.http.Host);
    } else {
        hash_data(server.ip);
    }

    # Hash by Accept-Encoding for proper compression handling
    if (req.http.Accept-Encoding) {
        hash_data(req.http.Accept-Encoding);
    }

    # Hash by device type for responsive content
    if (req.http.User-Agent ~ "(?i)(mobile|android|iphone|ipod)") {
        hash_data("mobile");
    }

    # Hash by language
    if (req.http.Accept-Language) {
        hash_data(regsub(req.http.Accept-Language, ",.*", ""));
    }

    return (lookup);
}

sub vcl_hit {
    # Grace mode - serve stale if backend is unhealthy
    if (obj.ttl >= 0s) {
        return (deliver);
    }

    # Stale content is still fresh for 60 seconds
    if (obj.ttl + obj.grace > 0s) {
        return (deliver);
    }

    # Fetch fresh content while delivering stale
    if (std.healthy(req.backend_hint)) {
        # Backend is healthy - fetch in background
        return (restart);
    }

    # Backend is sick - deliver stale content
    return (deliver);
}

sub vcl_miss {
    return (fetch);
}

sub vcl_backend_response {
    # Set cache TTL based on content type
    if (beresp.http.Cache-Control && beresp.http.Cache-Control ~ "(no-cache|no-store|private)") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    # Don't cache authenticated responses
    if (beresp.http.Set-Cookie ~ "(user_session|auth_token)") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }

    # Static assets - long TTL
    if (bereq.url ~ "\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot)$") {
        unset beresp.http.Set-Cookie;
        set beresp.ttl = 86400s;  # 24 hours
        set beresp.http.Cache-Control = "public, max-age=86400, immutable";
    }
    # API responses - short TTL
    elseif (bereq.url ~ "^/api/") {
        set beresp.ttl = 60s;  # 1 minute
        set beresp.http.Cache-Control = "public, max-age=60, s-maxage=60";
        set beresp.grace = 300s;  # 5 minutes grace
    }
    # HTML pages - medium TTL
    elseif (bereq.url ~ "\.html$" || bereq.url ~ "/$") {
        set beresp.ttl = 3600s;  # 1 hour
        set beresp.http.Cache-Control = "public, max-age=3600, s-maxage=3600";
        set beresp.grace = 86400s;  # 24 hours grace
    }
    # Default TTL
    else {
        set beresp.ttl = 1800s;  # 30 minutes
        set beresp.http.Cache-Control = "public, max-age=1800";
    }

    # Preserve cache tags for invalidation
    if (beresp.http.X-Cache-Tags) {
        set beresp.http.X-Cache-Tags = beresp.http.X-Cache-Tags;
    }

    # Add debug headers
    set beresp.http.X-Cache-Status = "MISS";
    set beresp.http.X-Cache-TTL = beresp.ttl;

    return (deliver);
}

sub vcl_deliver {
    # Add hit/miss info
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }

    # Add cache tags for debugging
    if (resp.http.X-Cache-Tags) {
        set resp.http.X-Cache-Tags = resp.http.X-Cache-Tags;
    }

    # Remove internal headers
    unset resp.http.X-Varnish;
    unset resp.http.Via;

    # Add security headers
    set resp.http.X-Content-Type-Options = "nosniff";
    set resp.http.X-Frame-Options = "SAMEORIGIN";
    set resp.http.X-XSS-Protection = "1; mode=block";

    # Add CORS headers for API
    if (req.url ~ "^/api/") {
        set resp.http.Access-Control-Allow-Origin = "*";
        set resp.http.Access-Control-Allow-Methods = "GET, POST, PUT, DELETE, OPTIONS";
        set resp.http.Access-Control-Allow-Headers = "Content-Type, Authorization";
    }

    return (deliver);
}

sub vcl_synth {
    if (resp.status == 750) {
        set resp.status = 301;
        set resp.http.Location = req.http.X-Redirect-Location;
        return (deliver);
    }

    set resp.http.Content-Type = "text/html; charset=utf-8";
    synthetic({"<!DOCTYPE HTML>
<html>
<head>
    <title>"} + resp.status + " " + resp.reason + {"</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        .error { color: #c00; }
    </style>
</head>
<body>
    <h1 class="error">"} + resp.status + " " + resp.reason + {"</h1>
    <p>"} + resp.reason + {"</p>
</body>
</html>
"});

    return (deliver);
}

sub vcl_backend_error {
    set beresp.http.Content-Type = "text/html; charset=utf-8";
    synthetic({"<!DOCTYPE HTML>
<html>
<head>
    <title>Backend Error</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 50px; }
        .error { color: #c00; }
    </style>
</head>
<body>
    <h1 class="error">503 Service Unavailable</h1>
    <p>The server is currently unavailable. Please try again later.</p>
</body>
</html>
"});

    return (deliver);
}
