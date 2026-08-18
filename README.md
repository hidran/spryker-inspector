# spryker-community/inspector

[Inspector APM](https://inspector.dev) integration for Spryker.

Reports Zed requests, console commands and queue workers as Inspector transactions, and records
AI prompts and tool calls made through `spryker/ai-foundation` as segments inside them — including
model, provider, token usage and cost data.

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
```

| Setting | Default | Purpose |
|---|---|---|
| `INGESTION_KEY` | `''` | Inspector ingestion key. Empty disables recording entirely. |
| `URL` | `https://ingest.inspector.dev` | Ingestion endpoint. Invalid URLs fall back to the default instead of throwing. |
| `TRANSPORT` | `async` | `async` (non-blocking, needs `proc_open`) or `sync` (blocking cURL). |
| `IS_ENABLED` | `true` | Master switch. |
| `ENABLED_APPLICATIONS` | `ZED` | Applications that report, matched against the `APPLICATION` constant. |
| `IGNORED_COMMANDS` | `[]` | Console commands not to report. Wildcards supported, e.g. `queue:*`. |

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

### 6. Rebuild caches

```bash
vendor/bin/console cache:empty-all
vendor/bin/console cache:class-resolver:build
```

## What gets reported

| Signal | Source |
|---|---|
| Transaction per Zed request | `setTransactionName()` from the Spryker monitoring stack |
| HTTP context (method, URL, headers) | `Transaction::markAsRequest()`, with cookies and authorization headers stripped |
| Transaction result | `success`, downgraded to `error` by `setError()` |
| Errors | `Spryker\Shared\ErrorHandler\ErrorLogger` → `setError()` |
| AI prompt segment (`agent.inference`) | `PostPromptPluginInterface`, duration from `PromptResponse.inferenceTimeMs` |
| AI token usage and cost | `Inspector\Models\Token` from `PromptResponse.message.usage` |
| AI tool call segment (`agent.tool`) | `PreToolCallPluginInterface` / `PostToolCallPluginInterface` |

AI segments use the `agent.` type prefix that Inspector's own Neuron observer uses
(`\Inspector\Neuron\InspectorObserver::SEGMENT_TYPE`), so they are classified as agent activity in
the dashboard. Inspector does not document this rule; it is taken from that reference implementation.

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

- Spryker never calls `markStartTransaction()` or `markEndOfTransaction()` in Zed, so the
  transaction result is set when the transaction is named and downgraded on error, rather than
  resolved at request end.
- `Configuration::maxItems` defaults to 150 entries per transaction; beyond that entries are
  dropped (errors exempt).
- There is no route-level ignore list, only a command-level one.
