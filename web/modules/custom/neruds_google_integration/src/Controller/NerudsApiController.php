<?php

declare(strict_types=1);

namespace Drupal\neruds_google_integration\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public JSON endpoints for portal components.
 */
final class NerudsApiController {

  public static function dashboard(): JsonResponse {
    return self::json(\Drupal::service('neruds_google_integration.portal_data')->getDashboard());
  }

  public static function territories(): JsonResponse {
    return self::json(\Drupal::service('neruds_google_integration.portal_data')->getTerritories());
  }

  public static function calendar(): JsonResponse {
    return self::json(\Drupal::service('neruds_google_integration.portal_data')->getCalendarEvents());
  }

  public static function calendarIcs(): Response {
    $events = \Drupal::service('neruds_google_integration.portal_data')->getCalendarEvents();
    $lines = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//NERUDS//Agenda Territorial//PT-BR',
      'CALSCALE:GREGORIAN',
      'METHOD:PUBLISH',
    ];

    foreach ($events as $event) {
      $start = self::icsDate($event['start'] ?? 'now');
      $end = self::icsDate($event['end'] ?? ($event['start'] ?? 'now'), '+1 hour');
      $uid = preg_replace('/[^A-Za-z0-9@._-]/', '-', (string) ($event['id'] ?? md5(($event['title'] ?? '') . $start))) . '@neruds.org';
      $lines[] = 'BEGIN:VEVENT';
      $lines[] = 'UID:' . $uid;
      $lines[] = 'DTSTAMP:' . gmdate('Ymd\THis\Z');
      $lines[] = 'DTSTART:' . $start;
      $lines[] = 'DTEND:' . $end;
      $lines[] = 'SUMMARY:' . self::icsEscape((string) ($event['title'] ?? 'Evento NERUDS'));
      if (!empty($event['url'])) {
        $lines[] = 'URL:' . self::icsEscape((string) $event['url']);
      }
      $description = $event['extendedProps']['description'] ?? $event['extendedProps']['territory'] ?? '';
      if ($description !== '') {
        $lines[] = 'DESCRIPTION:' . self::icsEscape((string) $description);
      }
      $lines[] = 'END:VEVENT';
    }
    $lines[] = 'END:VCALENDAR';

    $response = new Response(implode("\r\n", $lines) . "\r\n");
    $response->headers->set('Content-Type', 'text/calendar; charset=utf-8');
    $response->headers->set('Content-Disposition', 'attachment; filename="neruds-agenda.ics"');
    $response->setPublic();
    $response->setMaxAge(300);
    return $response;
  }

  public static function publicationMetrics(Request $request): JsonResponse {
    return self::json(\Drupal::service('neruds_google_integration.portal_data')->getPublicationMetrics(
      (string) $request->query->get('q', ''),
      self::publicationFilters($request),
    ));
  }

  public static function auditCitations(Request $request): JsonResponse {
    return self::json(\Drupal::service('neruds_google_integration.audit')->citations([
      'project' => (string) $request->query->get('project', ''),
      'sigilo' => (string) $request->query->get('sigilo', ''),
      'q' => (string) $request->query->get('q', ''),
      'limit' => (int) $request->query->get('limit', 20),
      'offset' => (int) $request->query->get('offset', 0),
    ]));
  }

  public static function auditSchema(): JsonResponse {
    return self::json(\Drupal::service('neruds_google_integration.audit')->schema());
  }

  public static function auditLookup(Request $request): JsonResponse {
    $id = (string) $request->query->get('id', '');
    return self::json(\Drupal::service('neruds_google_integration.audit')->citations([
      'q' => $id,
      'limit' => $id !== '' ? 1 : 20,
      'offset' => 0,
    ]));
  }

  public static function datasets(Request $request): JsonResponse {
    return self::json(\Drupal::service('neruds_google_integration.audit')->datasets([
      'project' => (string) $request->query->get('project', ''),
      'sigilo' => (string) $request->query->get('sigilo', ''),
      'q' => (string) $request->query->get('q', ''),
      'limit' => (int) $request->query->get('limit', 20),
      'offset' => (int) $request->query->get('offset', 0),
    ]));
  }

  public static function driveFolder(Request $request): JsonResponse {
    $folder = (string) $request->query->get('folder', '');
    return self::json(\Drupal::service('neruds_google_integration.portal_data')->getDriveFolder($folder));
  }

  public static function googleSearch(Request $request): JsonResponse {
    $query = (string) $request->query->get('q', '');
    $start = (int) $request->query->get('start', 1);
    return self::json(\Drupal::service('neruds_google_integration.portal_data')->googleSearch(
      $query,
      $start,
      self::publicationFilters($request),
    ));
  }

  public static function searchReadiness(): JsonResponse {
    return self::json(\Drupal::service('neruds_google_integration.portal_data')->getSearchReadiness());
  }

  public static function forumModerationStatus(): JsonResponse {
    return self::json(\Drupal::service('neruds_google_integration.portal_data')->getForumModerationStatus());
  }

  private static function publicationFilters(Request $request): array {
    return [
      'territory' => (string) $request->query->get('territory', ''),
      'ods' => (string) $request->query->get('ods', ''),
      'type' => (string) $request->query->get('type', ''),
      'year' => (string) $request->query->get('year', ''),
    ];
  }

  private static function json(array $data): JsonResponse {
    $response = new JsonResponse($data);
    $response->setPublic();
    $response->setMaxAge(300);
    $response->headers->set('X-Drupal-Cache-Tags', 'neruds_google_integration');
    return $response;
  }

  private static function icsDate(string $value, string $fallbackOffset = ''): string {
    $timestamp = strtotime($value);
    if ($timestamp === FALSE) {
      $timestamp = time();
    }
    if ($fallbackOffset !== '' && strtotime($value) !== FALSE && !str_contains($value, 'T')) {
      $timestamp = strtotime($fallbackOffset, $timestamp) ?: $timestamp;
    }
    return gmdate('Ymd\THis\Z', $timestamp);
  }

  private static function icsEscape(string $value): string {
    return str_replace(["\\", "\n", "\r", ',', ';'], ["\\\\", "\\n", '', "\\,", "\\;"], $value);
  }

}
