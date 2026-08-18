# Changelog

## 1.4.0

Reshapes AI reporting to match how Inspector actually presents agent activity: one call you open up
to reveal its tools, rather than tools listed alongside the call.

### Added

- `agent.workflow` segment per AI call, with the inference and every tool call nested inside it.
- The surrounding transaction is typed `agent`, which is what lists it under the dashboard's Agent
  section. Controlled by `INSPECTOR:IS_AGENT_TRANSACTION_TYPE_ENABLED`, on by default.
- Agent segments carry the colour Inspector's Neuron observer uses.
- `InspectorServiceInterface::startAgentToolSegment()`, `endAgentToolSegment()` and
  `recordAgentCall()`.

### Changed

- Segment labels follow the reference format: `inference( openai/gpt-4o-mini )` and
  `tool_call( get_content_items )`, previously the bare provider/model and tool name.
- Tool call context keys are `Inputs` and `Output`, previously `arguments` and `result`.
- `InspectorPostToolCallPlugin::SEGMENT_TYPE` is gone; the type is owned by the service now.
- A transaction that has become an agent transaction is no longer re-typed. Spryker types console
  transactions on `ConsoleTerminateEvent`, after the command has run, which otherwise relabelled an
  agent call as `command`.

### Notes

- Spryker exposes no pre-prompt extension point, so a call is only observable once it has finished,
  by which time its tool segments have opened and closed. They are held and re-parented onto the
  agent segment afterwards, which works because Inspector serializes segments at flush time.
- `agent.workflow` and `agent.inference` report the same duration, because Spryker exposes one
  `inferenceTimeMs` for the whole call rather than a figure per round trip.
- `http.client` segments stay beside the agent call rather than inside it. The Guzzle middleware has
  no way to know a prompt is running, and adopting every request made during one would wrongly pull
  unrelated calls in.

## 1.3.0

### Added

- `InspectorConsoleEventSubscriberPlugin` opens the transaction at `ConsoleEvents::COMMAND`, the
  counterpart of the Symfony bundle's `ConsoleEventsSubscriber`.
- `InspectorServiceInterface::isCommandIgnored()`.

### Notes

- Spryker reports console transactions on `ConsoleTerminateEvent`, once the command has already
  finished, so until now console runs arrived as a bare duration with no segments at all. This
  applies to queue workers too, which Spryker runs as console commands, so AI work triggered from
  the queue was invisible.
- The transaction is still renamed by Spryker to `vendor/bin/console <command>` at terminate, so
  transaction naming is unchanged.
- With this registered, an AI prompt that calls a tool produces the full agentic loop:
  `http.client` (model decides), `agent.tool` (tool runs), `http.client` (model continues),
  inside one `agent.inference`.

## 1.2.0

### Added

- `InspectorGuzzleMiddleware` reports outbound Guzzle requests as `http.client` segments, with
  method, host and status code. This is the counterpart of the Symfony bundle's
  `TraceableHttpClient`, which decorates the single `HttpClientInterface` service; Spryker has no
  such service, so the middleware is pushed onto the handler stacks you want traced.
- `InspectorServiceInterface::createGuzzleHandlerStack()`, for building or extending a traced stack
  where a container is available.
- `INSPECTOR:IS_HTTP_CLIENT_TRACKING_ENABLED` and `INSPECTOR:IS_HTTP_QUERY_TRACKING_ENABLED`.

### Notes

- AI provider calls are traced by passing the stack as `httpOptions.handler` in `AI_CONFIGURATIONS`,
  which separates time spent waiting on the provider from time spent in Spryker around it. AWS
  Bedrock is not covered: it goes through the AWS SDK rather than this client.
- HTTP error responses arrive as responses rather than exceptions, because `HandlerStack::create()`
  places Guzzle's `http_errors` middleware outside this one. A 401 is a completed segment with
  `status_code: 401`; only transport failures record `error` and a null status code.
- URLs are reported without their query string unless
  `INSPECTOR:IS_HTTP_QUERY_TRACKING_ENABLED` is set, and credentials in the userinfo component are
  always stripped.

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
