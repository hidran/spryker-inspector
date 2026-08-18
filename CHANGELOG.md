# Changelog

## 1.1.0

Brings the integration up to what the Inspector Symfony bundle reports, and adds Twig and Propel
tracing, which the bundle covers for Doctrine and Symfony's HTTP client instead.

### Added

- `InspectorEventDispatcherPlugin` reports the Zed request lifecycle: `process` and `controller`
  segments, the HTTP status code as the transaction result, the acting Back Office user, response
  context, and an explicit flush on `kernel.terminate`.
- `InspectorTwigPlugin` reports each rendered template as a `view.twig` segment.
- `InspectorConnectionWrapper` reports each executed Propel statement as a `db.propel` segment,
  with the operation and table extracted so the segments group in the dashboard.
- `INSPECTOR:IGNORED_TRANSACTIONS`, the request-side counterpart of `INSPECTOR:IGNORED_COMMANDS`.
- `INSPECTOR:MAX_ITEMS`, to raise the per-transaction entry limit when Twig or Propel tracking
  makes the default of 150 too low.
- `INSPECTOR:IS_TWIG_TRACKING_ENABLED`, `INSPECTOR:IS_PROPEL_TRACKING_ENABLED`,
  `INSPECTOR:PROPEL_SLOW_QUERY_THRESHOLD_MILLISECONDS` and
  `INSPECTOR:IS_QUERY_BINDINGS_TRACKING_ENABLED`.

### Changed

- Console transactions are now typed `command` instead of `console`, matching the type the Inspector
  Symfony bundle uses. Dashboard views or alerts filtering on `console` need updating.
- `markTransactionAsHttpRequest()` is idempotent, so HTTP context is attached once regardless of how
  many observation points call it.
- Discarding a transaction now also drops any segments still open against it, which previously could
  be closed against the next transaction.

### Notes

- Twig and Propel tracking are both off by default. Both add work to hot paths; see the README
  before enabling them in production.
- Propel statements are reported with placeholders rather than bound values. Enabling
  `INSPECTOR:IS_QUERY_BINDINGS_TRACKING_ENABLED` transmits the real values, including personal data.

## 1.0.2

- Restore the MIT file headers, which a `phpcbf` run against the Spryker coding standard had
  rewritten to the proprietary Spryker header in 1.0.0 and 1.0.1.
- Add a package `phpcs.xml` that excludes `Spryker.Commenting.FileDocBlock`, so linting cannot
  relicense the file headers again.

## 1.0.1

- Rename the AI segment types to `agent.inference` and `agent.tool`, the prefix Inspector's own
  Neuron observer uses to classify agent activity.

## 1.0.0

- Initial release: Zed request, console and queue transactions through
  `MonitoringExtensionPluginInterface`, and AI prompt and tool call segments through
  `spryker/ai-foundation`.
