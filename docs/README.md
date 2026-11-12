# Documentation

Documentation for the `dcplibrary/notices` package.

## Using it

| Document | For |
| --- | --- |
| [User guide](user-guide.md) | Staff using the web interface |
| [Workflows](workflows.md) | Day-to-day operation and troubleshooting |
| [Concepts](concepts.md) | The notification lifecycle, delivery options and issue types |

## Running it

| Document | For |
| --- | --- |
| [Commands](commands.md) | Every Artisan command and its options |
| [Deployment](deployment/README.md) | Docker setup, with example files |
| [API](api.md) | Configuring API access; endpoints are described in [openapi.yaml](openapi.yaml) |

## Developing it

Developer documentation is in [development/](development/).

| Document | For |
| --- | --- |
| [AGENTS.md](../AGENTS.md) | The rules that decide notification success and failure, and why. Read this first. |
| [Architecture](development/architecture.md) | How the code is laid out |
| [Contributing](development/contributing.md) | Commit format, releases and the stylesheet build |
| [Testing](development/testing.md) | Running the test suite |
| [User interface](development/ui.md) | Interface conventions specific to this package |

The PHP class reference is generated from the code by phpDocumentor and published to GitHub Pages; see [Contributing](development/contributing.md#class-reference).
