/**
 * XQUANTORIA Cloudflare Worker
 *
 * Features:
 * - Edge caching
 * - Response optimization
 * - Cache header management
 * - Geographic routing
 * - A/B testing support
 * - Bot detection
 * - Rate limiting
 */

// Configuration
const CONFIG = {
  backendUrl: 'https://your-backend.com',
  cacheTTL: 3600, // 1 hour
  staleWhileRevalidate: 86400, // 24 hours
  apiCacheTTL: 300, // 5 minutes
  staticCacheTTL: 86400, // 24 hours
  bypassPaths: ['/api/v1/auth', '/api/v1/admin', '/admin'],
  cacheablePaths: ['/api/v1/posts', '/api/v1/categories', '/api/v1/tags'],
  cdnDomains: ['cdn.xquantoria.com', 'assets.xquantoria.com'],
};

// Cache key generation
function generateCacheKey(request) {
  const url = new URL(request.url);
  const cacheKey = new Request(url.toString(), request);

  // Add variant for mobile
  const userAgent = request.headers.get('User-Agent') || '';
  const isMobile = /mobile|android|iphone/i.test(userAgent);

  if (isMobile) {
    cacheKey.headers.set('X-Device', 'mobile');
  }

  return cacheKey;
}

// Handle OPTIONS requests for CORS
function handleOptions(request) {
  const headers = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, Authorization',
    'Access-Control-Max-Age': '86400',
  };

  return new Response(null, { headers });
}

// Determine if request is cacheable
function isCacheable(request) {
  const url = new URL(request.url);
  const pathname = url.pathname;

  // Only cache GET requests
  if (request.method !== 'GET') {
    return false;
  }

  // Don't cache if Authorization header is present
  if (request.headers.has('Authorization')) {
    return false;
  }

  // Don't cache specific paths
  for (const path of CONFIG.bypassPaths) {
    if (pathname.startsWith(path)) {
      return false;
    }
  }

  return true;
}

// Get cache TTL based on content type
function getCacheTTL(url) {
  const pathname = url.pathname;

  // Static assets
  if (/\.(css|js|jpg|jpeg|png|gif|svg|woff|woff2|ttf|eot|ico)$/.test(pathname)) {
    return CONFIG.staticCacheTTL;
  }

  // API endpoints
  if (pathname.startsWith('/api/')) {
    return CONFIG.apiCacheTTL;
  }

  // Default TTL
  return CONFIG.cacheTTL;
}

// Optimize response headers
function optimizeHeaders(response, request) {
  const headers = new Headers(response.headers);

  // Add cache headers
  const url = new URL(request.url);
  const ttl = getCacheTTL(url);

  headers.set('Cache-Control', `public, max-age=${ttl}, s-maxage=${ttl}`);
  headers.set('X-Cache-Status', 'MISS');
  headers.set('X-Edge-Location', env CF-Ray).replace(/.*-/, '');

  // Add security headers
  headers.set('X-Content-Type-Options', 'nosniff');
  headers.set('X-Frame-Options', 'SAMEORIGIN');
  headers.set('X-XSS-Protection', '1; mode=block');

  // Add CORS for API requests
  if (url.pathname.startsWith('/api/')) {
    headers.set('Access-Control-Allow-Origin', '*');
    headers.set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
    headers.set('Access-Control-Allow-Headers', 'Content-Type, Authorization');
  }

  // Compression
  const acceptEncoding = request.headers.get('Accept-Encoding');
  if (acceptEncoding && acceptEncoding.includes('br')) {
    headers.set('Content-Encoding', 'br');
  } else if (acceptEncoding && acceptEncoding.includes('gzip')) {
    headers.set('Content-Encoding', 'gzip');
  }

  return headers;
}

// Transform response
function transformResponse(response, request) {
  const url = new URL(request.url);

  // Only transform HTML responses
  const contentType = response.headers.get('Content-Type') || '';
  if (!contentType.includes('text/html')) {
    return response;
  }

  return new Response(response.body, {
    status: response.status,
    statusText: response.statusText,
    headers: optimizeHeaders(response, request),
  });
}

// Fetch from origin with retry
async function fetchFromOrigin(request, retries = 3) {
  const originUrl = new URL(request.url);
  const backendUrl = new URL(CONFIG.backendUrl + originUrl.pathname + originUrl.search);

  const originRequest = new Request(backendUrl, request);

  // Add Cloudflare headers
  originRequest.headers.set('CF-Connecting-IP', request.headers.get('CF-Connecting-IP'));
  originRequest.headers.set('CF-Ray', request.headers.get('CF-Ray'));
  originRequest.headers.set('CF-IPCountry', request.headers.get('CF-IPCountry'));

  for (let i = 0; i < retries; i++) {
    try {
      const response = await fetch(originRequest);

      if (response.ok) {
        return transformResponse(response, request);
      }

      // Don't retry client errors
      if (response.status >= 400 && response.status < 500) {
        return response;
      }
    } catch (error) {
      if (i === retries - 1) {
        throw error;
      }
    }
  }
}

// Rate limiting
async function checkRateLimit(request) {
  const clientIP = request.headers.get('CF-Connecting-IP');
  const key = `rate_limit:${clientIP}`;

  // Get current count
  const count = await CACHE.get(key) || 0;
  const limit = 100; // 100 requests per minute

  if (count >= limit) {
    return new Response('Too Many Requests', {
      status: 429,
      headers: {
        'Retry-After': '60',
        'X-RateLimit-Limit': limit.toString(),
        'X-RateLimit-Remaining': '0',
      },
    });
  }

  // Increment counter
  await CACHE.put(key, (parseInt(count) + 1).toString(), { expirationTtl: 60 });

  return null;
}

// Bot detection
function detectBot(request) {
  const userAgent = request.headers.get('User-Agent') || '';

  const bots = [
    /googlebot/i,
    /bingbot/i,
    /slurp/i,
    /duckduckbot/i,
    /baiduspider/i,
    /yandexbot/i,
    /facebookexternalhit/i,
    /twitterbot/i,
    /linkedinbot/i,
  ];

  for (const botPattern of bots) {
    if (botPattern.test(userAgent)) {
      return true;
    }
  }

  return false;
}

// Geographic routing
function getRegionalBackend(request) {
  const country = request.headers.get('CF-IPCountry');

  const regionalBackends = {
    US: 'https://us-backend.xquantoria.com',
    EU: 'https://eu-backend.xquantoria.com',
    AS: 'https://asia-backend.xquantoria.com',
  };

  return regionalBackends[country] || CONFIG.backendUrl;
}

// A/B testing
function getVariant(request) {
  const userId = request.headers.get('X-User-ID');
  const cookie = request.headers.get('Cookie');

  if (cookie && cookie.includes('ab_test_variant=')) {
    const match = cookie.match(/ab_test_variant=([^;]+)/);
    return match ? match[1] : 'A';
  }

  // Assign variant based on user ID hash
  if (userId) {
    const hash = hashString(userId);
    return hash % 2 === 0 ? 'A' : 'B';
  }

  return 'A';
}

function hashString(str) {
  let hash = 0;
  for (let i = 0; i < str.length; i++) {
    const char = str.charCodeAt(i);
    hash = ((hash << 5) - hash) + char;
    hash = hash & hash;
  }
  return Math.abs(hash);
}

// Main event listener
addEventListener('fetch', (event) => {
  event.respondWith(handleRequest(event.request));
});

async function handleRequest(request) {
  // Handle CORS preflight
  if (request.method === 'OPTIONS') {
    return handleOptions(request);
  }

  // Detect bot
  const isBot = detectBot(request);
  if (isBot) {
    // Serve static content to bots
    request.headers.set('X-Bot-Detected', 'true');
  }

  // Rate limiting
  const rateLimitResponse = await checkRateLimit(request);
  if (rateLimitResponse) {
    return rateLimitResponse;
  }

  // Generate cache key
  const cacheKey = generateCacheKey(request);

  // Check if request is cacheable
  if (!isCacheable(request)) {
    return fetchFromOrigin(request);
  }

  // Try to get from cache
  const cached = await cache.match(cacheKey);

  if (cached) {
    // Add stale warning
    const headers = new Headers(cached.headers);
    headers.set('X-Cache-Status', 'HIT');

    return new Response(cached.body, {
      status: cached.status,
      statusText: cached.statusText,
      headers,
    });
  }

  // Fetch from origin
  try {
    const response = await fetchFromOrigin(request);

    // Cache successful responses
    if (response.ok && isCacheable(request)) {
      const ttl = getCacheTTL(new URL(request.url));
      response.headers.set('Cache-Control', `public, max-age=${ttl}, s-maxage=${ttl}, stale-while-revalidate=${CONFIG.staleWhileRevalidate}`);

      // Store in cache
      event.waitUntil(
        cache.put(cacheKey, response.clone())
      );
    }

    return response;
  } catch (error) {
    // Return stale cache if available on error
    const stale = await cache.match(cacheKey);

    if (stale) {
      const headers = new Headers(stale.headers);
      headers.set('X-Cache-Status', 'STALE');
      headers.set('X-Cache-Error', 'origin_error');

      return new Response(stale.body, {
        status: stale.status,
        statusText: stale.statusText,
        headers,
      });
    }

    // Return error response
    return new Response('Service Unavailable', {
      status: 503,
      headers: {
        'Retry-After': '60',
      },
    });
  }
}

// Export for testing
export {
  generateCacheKey,
  isCacheable,
  getCacheTTL,
  detectBot,
  getVariant,
};
