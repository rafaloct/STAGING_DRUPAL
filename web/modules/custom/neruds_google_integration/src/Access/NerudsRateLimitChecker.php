<?php

declare(strict_types=1);

namespace Drupal\neruds_google_integration\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class NerudsRateLimitChecker {

  public function __construct(
    private CacheBackendInterface $cache,
    private RequestStack $requestStack,
  ) {}

  public function access(RouteMatchInterface $route, AccountInterface $account): AccessResult {
    $limit = $route->getRouteObject()->getRequirement('_neruds_rate_limit');
    if (!$limit) {
      return AccessResult::neutral();
    }

    [$requests, $window] = array_map('intval', explode('/', $limit));
    $request = $this->requestStack->getCurrentRequest();
    $clientIp = $request ? $request->getClientIp() : 'unknown';
    $routeName = $route->getRouteName();
    $cacheKey = "neruds_rate_limit:{$routeName}:{$clientIp}";

    $cached = $this->cache->get($cacheKey);
    $count = $cached ? (int) $cached->data : 0;

    if ($count >= $requests) {
      return AccessResult::forbidden('Rate limit exceeded');
    }

    $this->cache->set($cacheKey, $count + 1, \Drupal::time()->getRequestTime() + $window);
    return AccessResult::allowed()->cachePerUser();
  }
}
