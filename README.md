# spryker-community/inspector

[Inspector APM](https://inspector.dev) integration for Spryker.

Reports Zed requests, console commands and queue workers as Inspector transactions, broken down
into segments for the request lifecycle, rendered templates, database statements and AI activity.

| Segment type | What it reports |
|---|---|
| `process` | `kernel.request` and `kernel.response` phases |
| `controller` | the controller action, as `module/controller/action` |
| `view.twig` | each rendered Twig template |
| `db.propel` | each executed database statement, with operation and table |
| `http.client` | each outbound Guzzle request, with method, host and status code |
| `agent.workflow` | each AI call, with its inference and tool calls nested inside |
| `agent.inference` | the prompt itself, with provider, model and token usage |
| `agent.tool` | each tool the model called, with inputs and output |

## Why not the Inspector Symfony bundle

`inspector-apm/inspector-symfony` ships an `InspectorBundle`, and Spryker can register Symfony
bundles via `config/Zed/bundles.php`. It does not work here:

- `KernelEventsSubscriber` requires a `Symfony\Component\Security\Core\Security` **service**. The
  extension branches on `class_exists()`, but Spryker's Zed container registers no Symfony security
  services at all (`security.token_storage`, `TokenStorageInterface` and `security.helper` are all
  absent), so container compilation fails.
- `InspectorExtension::prepend()` unconditionally adds `MessengerMonitoringMiddleware` to
  `messenger.bus.default` whenever `symfony/messenger` is installed, while `load()` only defines
  that service when `messenger: true`. Setting it to `false` fails with
  `Invalid middleware: service ... not found`.
- The Doctrine and Twig tracers cannot bind: Spryker uses Propel, and `twig` is not a service in
  the Zed Symfony container.

This package instead plugs into `MonitoringExtensionPluginInterface`, which Spryker already drives
for Zed HTTP, console and queue runtimes.

## Requirements

- PHP >= 8.3
- `spryker/kernel`, `spryker/log`, `spryker/monitoring-extension`
- `spryker/monitoring` to report request and console transactions
- `spryker/ai-foundation` (optional) to report AI prompts and tool calls

## Installation

```bash
composer require spryker-community/inspector
```

### 1. Register the namespace

Spryker's class resolver only searches configured namespaces. In `config/Shared/config_default.php`:

```php
$config[KernelConstants::CORE_NAMESPACES] = [
    'SprykerShop',
    'SprykerEco',
    'SprykerCommunity',
    'Spryker',
    'SprykerSdk',
    'SprykerFeature',
];
```

### 2. Configure

```php
use SprykerCommunity\Shared\Inspector\InspectorConstants;

$inspectorIsEnabled = getenv('INSPECTOR_IS_ENABLED');

$config[InspectorConstants::INGESTION_KEY] = getenv('INSPECTOR_INGESTION_KEY') ?: '';
$config[InspectorConstants::URL] = getenv('INSPECTOR_URL') ?: 'https://ingest.inspector.dev';
$config[InspectorConstants::TRANSPORT] = getenv('INSPECTOR_TRANSPORT') ?: 'async';
$config[InspectorConstants::IS_ENABLED] = $inspectorIsEnabled === false
    ? true
    : filter_var($inspectorIsEnabled, FILTER_VALIDATE_BOOLEAN);
$config[InspectorConstants::ENABLED_APPLICATIONS] = array_filter(
    explode(',', getenv('INSPECTOR_ENABLED_APPLICATIONS') ?: 'ZED'),
);
$config[InspectorConstants::IGNORED_COMMANDS] = array_filter(
    explode(',', getenv('INSPECTOR_IGNORED_COMMANDS') ?: ''),
);
$config[InspectorConstants::IGNORED_TRANSACTIONS] = array_filter(
    explode(',', getenv('INSPECTOR_IGNORED_TRANSACTIONS') ?: 'heartbeat/*'),
);
$config[InspectorConstants::MAX_ITEMS] = (int)(getenv('INSPECTOR_MAX_ITEMS') ?: 150);
$config[InspectorConstants::IS_TWIG_TRACKING_ENABLED] = filter_var(
    getenv('INSPECTOR_IS_TWIG_TRACKING_ENABLED') ?: false,
    FILTER_VALIDATE_BOOLEAN,
);
$config[InspectorConstants::IS_PROPEL_TRACKING_ENABLED] = filter_var(
    getenv('INSPECTOR_IS_PROPEL_TRACKING_ENABLED') ?: false,
    FILTER_VALIDATE_BOOLEAN,
);
$config[InspectorConstants::PROPEL_SLOW_QUERY_THRESHOLD_MILLISECONDS] = (float)(
    getenv('INSPECTOR_PROPEL_SLOW_QUERY_THRESHOLD_MILLISECONDS') ?: 0
);
$config[InspectorConstants::IS_AGENT_TRANSACTION_TYPE_ENABLED] = filter_var(
    getenv('INSPECTOR_IS_AGENT_TRANSACTION_TYPE_ENABLED') ?: true,
    FILTER_VALIDATE_BOOLEAN,
);
$config[InspectorConstants::IS_HTTP_CLIENT_TRACKING_ENABLED] = filter_var(
    getenv('INSPECTOR_IS_HTTP_CLIENT_TRACKING_ENABLED') ?: false,
    FILTER_VALIDATE_BOOLEAN,
);
```

| Setting | Default | Purpose |
|---|---|---|
| `INGESTION_KEY` | `''` | Inspector ingestion key. Empty disables recording entirely. |
| `URL` | `https://ingest.inspector.dev` | Ingestion endpoint. Invalid URLs fall back to the default instead of throwing. |
| `TRANSPORT` | `async` | `async` (non-blocking, needs `proc_open`) or `sync` (blocking cURL). |
| `IS_ENABLED` | `true` | Master switch. |
| `ENABLED_APPLICATIONS` | `ZED` | Applications that report, matched against the `APPLICATION` constant. |
| `IGNORED_COMMANDS` | `[]` | Console commands not to report. Wildcards supported, e.g. `queue:*`. |
| `IGNORED_TRANSACTIONS` | `[]` | Zed transactions not to report, matched against `module/controller/action`. Wildcards supported. |
| `MAX_ITEMS` | `150` | Entries per transaction before further ones are dropped. Raise it when Twig or Propel tracking is on. |
| `IS_TWIG_TRACKING_ENABLED` | `false` | Report rendered templates as `view.twig` segments. |
| `IS_PROPEL_TRACKING_ENABLED` | `false` | Report database statements as `db.propel` segments. |
| `PROPEL_SLOW_QUERY_THRESHOLD_MILLISECONDS` | `0` | Only report statements at least this slow. `0` reports all of them. |
| `IS_QUERY_BINDINGS_TRACKING_ENABLED` | `false` | Interpolate bound values into reported statements. **Sends personal data — see below.** |
| `IS_AGENT_TRANSACTION_TYPE_ENABLED` | `true` | Type a transaction that ran an AI call as `agent`, which is what lists it under the dashboard Agent section. |
| `IS_HTTP_CLIENT_TRACKING_ENABLED` | `false` | Report outbound Guzzle requests as `http.client` segments. |
| `IS_HTTP_QUERY_TRACKING_ENABLED` | `false` | Include query strings in reported URLs. **Sends API keys and personal data — see below.** |

**`ENABLED_APPLICATIONS` matters.** The monitoring plugin is registered in the Service layer, which
every application shares. Without this list Yves, Glue and the Merchant Portal are monitored too —
every storefront request becomes a transaction. `ZED` covers the Back Office, Zed, the Backend
Gateway, console commands and queue workers.

`IGNORED_COMMANDS` is for scheduler-driven commands. On a standard install the Jenkins scheduler
starts `symfonymessenger:consume` every minute, which is one transaction per run. Note that the
same command is the queue worker, so ignoring it also hides AI calls triggered by queued work.

### 3. Register the monitoring plugin

`src/Pyz/Service/Monitoring/MonitoringDependencyProvider.php`:

```php
use SprykerCommunity\Service\Inspector\Plugin\InspectorMonitoringExtensionPlugin;

protected function getMonitoringExtensions(): array
{
    return [
        new InspectorMonitoringExtensionPlugin(),
    ];
}
```

### 4. Register the console command (optional)

`src/Pyz/Zed/Console/ConsoleDependencyProvider.php`:

```php
use SprykerCommunity\Zed\Inspector\Communication\Console\InspectorTestConsole;

$commands[] = new InspectorTestConsole();
```

Then verify the integration:

```bash
vendor/bin/console inspector:test
```

### 5. Register the AI plugins (optional, needs `spryker/ai-foundation`)

`src/Pyz/Zed/AiFoundation/AiFoundationDependencyProvider.php`:

```php
use SprykerCommunity\Zed\Inspector\Communication\Plugin\AiFoundation\InspectorPostPromptPlugin;
use SprykerCommunity\Zed\Inspector\Communication\Plugin\AiFoundation\InspectorPostToolCallPlugin;
use SprykerCommunity\Zed\Inspector\Communication\Plugin\AiFoundation\InspectorPreToolCallPlugin;

protected function getPostPromptPlugins(): array
{
    return [new InspectorPostPromptPlugin()];
}

protected function getPreToolCallPlugins(): array
{
    return [new InspectorPreToolCallPlugin()];
}

protected function getPostToolCallPlugins(): array
{
    return [new InspectorPostToolCallPlugin()];
}
```

### 6. Report the request lifecycle (recommended)

Without this the transactions carry no segments, and the result is `success` or `error` rather than
the HTTP status code. In `src/Pyz/Zed/EventDispatcher/EventDispatcherDependencyProvider.php`, add it
to every stack that already registers `MonitoringRequestTransactionEventDispatcherPlugin`:

```php
use SprykerCommunity\Zed\Inspector\Communication\Plugin\EventDispatcher\InspectorEventDispatcherPlugin;

protected function getBackofficeEventDispatcherPlugins(): array
{
    return [
        // ...
        new InspectorEventDispatcherPlugin(),
    ];
}
```

This adds `process` and `controller` segments, the response status code as the transaction result,
the acting Back Office user, and an explicit flush on `kernel.terminate`.

### 7. Report rendered templates (optional)

`src/Pyz/Zed/Twig/TwigDependencyProvider.php`:

```php
use SprykerCommunity\Zed\Inspector\Communication\Plugin\Twig\InspectorTwigPlugin;

protected function getTwigPlugins(): array
{
    return [
        // ...
        new InspectorTwigPlugin(),
    ];
}
```

Then set `INSPECTOR_IS_TWIG_TRACKING_ENABLED=1`. Twig instruments templates **at compile time**, so
the Twig cache must be rebuilt after enabling or disabling it:

```bash
vendor/bin/console cache:empty-all
vendor/bin/console twig:cache:warmer
```

A Back Office page renders roughly 30 templates, so raise `MAX_ITEMS` accordingly.

### 8. Report database statements (optional)

Propel builds its connection through `ConnectionFactory`, which honours a `classname` option. That
is the only injection point available, so this is configured rather than registered as a plugin.

It has to go in **`config/Shared/config_propel.php`**, not `config_default.php`: Spryker loads
`config_propel.php` last and it reassigns the whole connection array, discarding anything set
earlier.

```php
use SprykerCommunity\Shared\Inspector\InspectorConstants;
use SprykerCommunity\Zed\Inspector\Persistence\Propel\InspectorConnectionWrapper;

// after $config[PropelConstants::PROPEL]['database']['connections'][...] is assigned
if (!empty($config[InspectorConstants::IS_PROPEL_TRACKING_ENABLED])) {
    $config[PropelConstants::PROPEL]['database']['connections']['default']['classname'] = InspectorConnectionWrapper::class;
    $config[PropelConstants::PROPEL]['database']['connections']['zed']['classname'] = InspectorConnectionWrapper::class;
}
```

Then set `INSPECTOR_IS_PROPEL_TRACKING_ENABLED=1`.

**Read this before enabling it in production.** This class runs inside every database call the
application makes. Reporting failures are contained and switch tracking off for the rest of the
process rather than surfacing as query failures, but the code is still on the hot path.

- A single Back Office page issues 15–55 statements. With `MAX_ITEMS` at its default of 150 these
  will crowd out other segments; either raise the limit or set
  `PROPEL_SLOW_QUERY_THRESHOLD_MILLISECONDS` so only slow statements are reported.
- Statements are reported with placeholders (`WHERE username=:p1`), which is what makes them group
  in the dashboard. `IS_QUERY_BINDINGS_TRACKING_ENABLED` interpolates the real values instead:
  that transmits whatever the query contained — customer emails, addresses, order references — to
  Inspector, and stops the statements grouping. It is off by default and should stay off unless you
  have a specific reason and have checked it against your data protection obligations.

### 9. Report console commands and queue workers (recommended)

Spryker reports console transactions on `ConsoleTerminateEvent`, once the command has already
finished. Nothing a command does can be recorded against a transaction that does not exist yet, so
without this plugin console runs arrive as a bare duration — no database, HTTP, prompt or tool-call
segments. That covers **queue workers**, which Spryker runs as console commands, so AI work
triggered from the queue is otherwise invisible.

`src/Pyz/Zed/Console/ConsoleDependencyProvider.php`:

```php
use SprykerCommunity\Zed\Inspector\Communication\Plugin\Console\InspectorConsoleEventSubscriberPlugin;

public function getEventSubscriber(Container $container): array
{
    return [
        new MonitoringConsolePlugin(),
        new InspectorConsoleEventSubscriberPlugin(),
    ];
}
```

The transaction is opened at `ConsoleEvents::COMMAND` and Spryker renames it to its own
`vendor/bin/console <command>` form at terminate, so transaction naming does not change. The
command's arguments and options are attached as context.

### 10. Report outbound HTTP requests (optional)

The Symfony bundle traces outbound HTTP by decorating the one `HttpClientInterface` service in the
container. Spryker has no such service — `spryker/guzzle` is a metapackage with no code, and every
module builds its own client — so this ships as a Guzzle middleware that you push onto the handler
stack of the clients you want traced.

Set `INSPECTOR_IS_HTTP_CLIENT_TRACKING_ENABLED=1`, then wire it where the client is built.

**AI provider calls.** This is usually the one worth having, because it separates time spent waiting
on the provider from time spent in Spryker around it. `AI_CONFIGURATIONS` is plain PHP config, and
`ProviderResolver::createHttpClient()` passes `httpOptions.handler` straight to NeuronAI's Guzzle
client, so in `config/Shared/config_ai.php`:

```php
use GuzzleHttp\HandlerStack;
use SprykerCommunity\Service\Inspector\Guzzle\InspectorGuzzleMiddleware;

$inspectorHandlerStack = HandlerStack::create();
$inspectorHandlerStack->push(new InspectorGuzzleMiddleware());

$config[AiFoundationConstants::AI_CONFIGURATIONS] = [
    'my-configuration' => [
        'provider_name' => AiFoundationConstants::PROVIDER_OPENAI,
        'provider_config' => [
            // ...
            'httpOptions' => [
                'handler' => $inspectorHandlerStack,
            ],
        ],
    ],
];
```

Supported for the `openai`, `anthropic`, `deepseek`, `huggingface`, `mistral`, `ollama`, `grok`,
`azure` and `gemini` providers. Bedrock goes through the AWS SDK, not this client, and is not
covered. Supplying `httpOptions` makes AiFoundation build its own Guzzle client, using the same
60s/10s timeouts NeuronAI defaults to, so this does not change request behaviour.

**Anywhere else**, build the stack from the service so the middleware gets the container instance:

```php
$client = new Client([
    'handler' => $inspectorService->createGuzzleHandlerStack(),
]);
```

Or push it onto a stack you already have: `$inspectorService->createGuzzleHandlerStack($myStack)`.

What lands in the trace:

```
segment:controller     2477ms  ai-commerce/category-suggestion/index
segment:http.client    2398ms  POST https://api.openai.com/v1/chat/completions
segment:agent.inference 2407ms openai/gpt-4o-mini
```

Notes on behaviour:

- `HandlerStack::create()` places Guzzle's own `http_errors` middleware outside this one, so HTTP
  error responses arrive here as responses, not exceptions. A 401 is recorded as a completed
  segment with `status_code: 401`; only transport failures (DNS, connection refused, timeout) take
  the failure path, where `status_code` is `null` and the exception is recorded in `error`.
- Segments are recorded when the promise settles rather than held open, so concurrent requests
  cannot close each other's segments.
- Retries appear as separate segments, which is how you spot a provider being hammered.
- The label carries no query string, so requests to the same endpoint group together. Credentials
  in the URL userinfo component are always stripped. Query strings are dropped entirely unless
  `IS_HTTP_QUERY_TRACKING_ENABLED` is set — they routinely carry API keys and personal data.

### 11. Rebuild caches

```bash
vendor/bin/console cache:empty-all
vendor/bin/console cache:class-resolver:build
```

## What gets reported

| Signal | Source | Needs |
|---|---|---|
| Transaction per Zed request | `setTransactionName()` from the Spryker monitoring stack | step 3 |
| HTTP context (method, URL, headers) | `Transaction::markAsRequest()`, with cookies and authorization headers stripped | step 3 |
| Transaction type `command` for console | `markAsConsoleCommand()` | step 3 |
| Errors | `Spryker\Shared\ErrorHandler\ErrorLogger` → `setError()` | step 3 |
| `process` and `controller` segments | `kernel.request`, `kernel.controller`, `kernel.response` | step 6 |
| Transaction result as HTTP status code | `kernel.terminate` | step 6 |
| Acting Back Office user | `UserFacade::getCurrentUser()` → `Transaction::withUser()` | step 6 |
| Response context (status, content type) | `kernel.response` | step 6 |
| `view.twig` segment per template | Twig's `ProfilerNodeVisitor` | step 7 |
| `db.propel` segment per statement | Propel connection `classname` | step 8 |
| Console transaction opened at command start | `ConsoleEvents::COMMAND` | step 9 |
| `http.client` segment per outbound request | Guzzle handler stack middleware | step 10 |
| Agent call (`agent.workflow`) with nested inference and tools | `PostPromptPluginInterface`, duration from `PromptResponse.inferenceTimeMs` | step 5 |
| AI token usage and cost | `Inspector\Models\Token` from `PromptResponse.message.usage` | step 5 |
| AI tool call segment (`agent.tool`) | `PreToolCallPluginInterface` / `PostToolCallPluginInterface` | step 5 |

Without step 6 the transaction result stays `success`, downgraded to `error` by `setError()`,
because Spryker itself never signals the end of a Zed request.

### How AI calls are shaped

Inspector presents agent activity as one call you open up to reveal what it did, not as a flat list
of siblings. That shape comes from two things its Neuron observer does
(`\Inspector\Neuron\InspectorObserver`), both reproduced here:

- every AI segment uses the `agent.` type prefix and the observer's colour, and
- the surrounding transaction is typed `agent`, which is what lists it under the dashboard's Agent
  section.

A prompt that calls a tool arrives like this:

```
transaction [type=agent]  ai-commerce/category-suggestion/index
    process          kernel.request
    controller       ai-commerce/category-suggestion/index
        http.client      POST https://api.openai.com/v1/chat/completions
        http.client      POST https://api.openai.com/v1/chat/completions
        agent.workflow   <ai configuration name>
            agent.tool       tool_call( get_content_items )
            agent.inference  inference( openai/gpt-4o-mini )
    process          kernel.response
```

Two details are worth knowing:

**Nesting is reconstructed, not natural.** Inspector nests segments by the order they open and
close, but Spryker has no pre-prompt extension point: a call is only observable once it has
finished, by which time its tool segments have opened and closed. They are therefore held and
re-parented onto the `agent.workflow` segment afterwards, which works because Inspector serializes
segments at flush time.

**`agent.workflow` and `agent.inference` report the same duration.** Spryker exposes a single
`inferenceTimeMs` for the whole call rather than a figure per round trip, so the call and its
inference necessarily span the same window. The nesting is kept anyway because it is the shape the
dashboard expects.

**Typing the transaction `agent` replaces its original type.** In Zed the transaction is a whole
Back Office request or console command, so a request that happens to make one AI call is reported
as an agent transaction rather than a `request` or `command` one. Inspector's own agent monitoring
does exactly this. Set `IS_AGENT_TRANSACTION_TYPE_ENABLED` to `false` to keep the original type, at
the cost of the Agent section staying empty.

Cookies and `Authorization`-style headers are removed before sending. `markAsRequest()` captures
`$_COOKIE` and `apache_request_headers()` verbatim, which in Zed includes the Back Office session
cookie.

## Extending

`InspectorServiceInterface` is the entry point for reporting your own segments:

```php
$inspectorService->addCompletedSegment('agent.inference', 'openai/gpt-4o', 1234.5, ['model' => 'gpt-4o']);
$inspectorService->startSegment('integration', 'erp-sync');
$inspectorService->endOpenSegment('integration', 'erp-sync', ['status' => 'ok']);
```

## Known limitations

- Spryker never calls `markStartTransaction()` or `markEndOfTransaction()` in Zed. Without the
  event dispatcher plugin (step 6) the transaction result is set when the transaction is named and
  downgraded on error, rather than resolved at request end.
- Console commands get no lifecycle segments of their own. The transaction is opened at
  `ConsoleEvents::COMMAND` (step 9) so everything the command does is recorded, but the command
  body is not itself wrapped in a segment.
- Transactions are ignored by Spryker's `module/controller/action` name, which is only known at
  `kernel.controller`. Anything recorded before that point is discarded along with the transaction.
- Twig segments cover templates, not blocks or macros. Reporting every block would exhaust
  `MAX_ITEMS` on any real page.
- Outbound `http.client` segments sit beside the agent call rather than inside it. They are
  recorded by the Guzzle middleware, which has no way to know a prompt is running, and adopting
  every request made during a prompt would wrongly pull unrelated calls into the agent call.
- Outbound HTTP tracing is opt-in per client. The Symfony bundle decorates a single
  `HttpClientInterface` service and covers the whole application; Spryker has no equivalent, so the
  middleware has to be pushed onto each handler stack you want traced.
- AWS Bedrock AI calls are not traced. They go through the AWS SDK rather than the Guzzle client
  that `httpOptions` configures.
