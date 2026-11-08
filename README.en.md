# Docker Inventory for GLPI

[🇫🇷 Français](README.md)

GLPI plugin that gives a **read-only** view of containerised applications:

- **Portainer**: Docker Swarm stacks and services, cluster nodes;
- **What's Up Docker (WUD)**: deployed version and latest available version of each image;
- **Harbor**: projects, repositories, artifacts and vulnerability scan overview of the images.

It answers "what is running, in which version, is it up to date, is it vulnerable?", which GLPI
cannot do on its own: its "Virtualization" tab lists isolated containers, with no stack, service,
replicas or version.

> **Status**: version 0.1.0 released with batch 1 (Portainer). The core of batch 2 (What's Up Docker:
> image versions) is ready for the next release; registries without WUD, tickets and Harbor follow.
> See [ROADMAP.md](ROADMAP.md).

## Principles

- **Read-only.** The plugin never redeploys, restarts or deletes anything. WUD automatic updates are
  not used.
- **Nothing is changed automatically in GLPI either.** A service that disappears is marked
  "retired", not deleted. Version gaps lead to a proposed ticket above chosen thresholds, never to a
  status, priority or assignment change.
- **Least-privilege accounts.** Dedicated read-only user in Portainer, WUD and Harbor; API keys
  encrypted with the GLPI key; Docker secret names imported, never their content.
- **Fictitious data** in tests, screenshots and documentation.

## What the plugin does

| Source | Import | In GLPI |
| --- | --- | --- |
| Portainer | Stacks, services (image, tag, digest, replicas, published ports, networks, secret names), nodes | Stack = native Appliance (impact analysis); Service form; nodes linked to inventoried computers |
| WUD | Watched containers: local tag and digest, latest version, kind of gap | Deployed version, latest version, gap (patch, minor, major, digest only, not trackable) per service; containers without a service listed separately |
| Registries (without WUD) | Docker Hub and Harbor v2 API | Same version table, computed by the plugin |
| Harbor | Projects, repositories, artifacts, vulnerability scan overview | Vulnerabilities per image, images outside the managed registry flagged |

## Compatibility

- GLPI 11.0 and 12.x
- PHP 8.2 to 8.5 (GLPI 11), 8.3 to 8.5 (GLPI 12)
- Portainer CE or BE (API 2.x), WUD 8.x, Harbor 2.x

Portainer CE only has coarse roles: the read-only user sees more than needed there. The
installation guide will say so.

## Installation

Download the `glpi-docker-inventory-X.Y.Z.zip` archive from the [latest release](https://github.com/parime/glpi-docker-inventory/releases/latest) (its
SHA-256 checksum is published next to it), then unzip it into the GLPI `plugins` folder:

```sh
cd /var/www/glpi/plugins
unzip glpi-docker-inventory-X.Y.Z.zip   # creates the dockerinventory/ folder
```

Then **Setup > Plugins**, install and enable "Docker Inventory".

## Usage (batch 1: Portainer)

1. **In Portainer**, create a dedicated user with minimal rights (read-only on the environments to
   inventory) and an access token for it (*My account > Access tokens*).
2. **In GLPI**, *Setup > Docker connections* (or the plugin name in *Setup > Plugins*): "Add",
   choose **Portainer**, then enter the Portainer address (`https://portainer.exemple.test:9443`)
   and the key. Pick
   the environment (Production, Staging...) given to the appliances the import will create. "Test
   the connection", then "Import now".
3. The import then runs **every hour** (*Setup > Automatic actions*, `portainerimport`).

What you get:

- *Assets > Container services*: every service, with image, tag, digest, replicas, status;
- on each **Appliance** created for a stack, the "Services" tab;
- on each **computer** whose name matches a Swarm node, the "Node services" tab.

What the plugin writes **outside its own tables**: only the one-time creation of a stack's
Appliance when no appliance with that name exists in the connection's entity. An existing
appliance, a computer or an environment is never modified or created. The read right on services
is granted in *Administration > Profiles*, "Docker Inventory" tab; connections require the *Setup*
update right.

## Usage (batch 2: What's Up Docker)

[What's Up Docker](https://github.com/getwud/wud) (WUD) watches container images and knows the
latest published version of each one. The plugin reads that result **without changing anything in
WUD** (no trigger, no update) and links it to the services imported from Portainer.

1. **Import Portainer first**: containers seen by WUD are linked to the imported Swarm services (by
   image digest, then by Swarm service name, then by image and tag).
2. **In GLPI**, *Setup > Docker connections*: "Add", choose **What's Up Docker**, enter the WUD
   address (`https://wud.exemple.test:3000`) and, if WUD requires authentication, the user and
   password (encrypted with the GLPI key, never shown again). "Test the connection" shows the WUD
   version and the number of watched containers, then "Read WUD now".
3. The read then runs **every hour** (*Setup > Automatic actions*, `wudimport`).

What you get:

- *Assets > Image versions*: for each service, deployed version, latest version and gap (coloured
  badges: major, minor, patch, digest only, not trackable, up to date), filters by gap and by
  appliance environment, containers without a service listed separately; "Advanced search and
  export" opens the GLPI search on the same columns;
- on each **service** and each **appliance**, the "Versions" tab.

Reading rules:

- the gap computed by WUD is taken as is; the plugin only computes it when WUD does not give it;
- a moving tag (`latest`, `stable`...) or one without a version number is "not trackable": only the
  digest tells whether the image changed;
- several containers of one service give a single row; replicas in different versions (update in
  progress or stuck) are flagged;
- a container without a matching service (outside Swarm, or several possible services) is not
  ignored: it is listed separately with the reason.

> The mapping of the WUD API (`/api/containers`, `/api/app`) follows the public WUD 8.x
> documentation and a fake WUD (`tests/mock-wud`); it still has to be validated against a real WUD
> 8.x instance ([#2](https://github.com/parime/glpi-docker-inventory/issues/2)).

The read right on services (*Administration > Profiles*, "Docker Inventory" tab) also gives access
to the versions.

**Portainer or WUD on a private network (GLPI 12).** By default GLPI 12 refuses calls to a private
address. To allow them for Portainer and WUD only, add to `config/local_define.php`:

```php
define('GLPI_SERVERSIDE_URL_ALLOWED_PRIVATE_NETWORKS_CONTEXTS', [
    \GlpiPlugin\Dockerinventory\Portainer\PortainerContext::class,
    \GlpiPlugin\Dockerinventory\Wud\WudContext::class,
]);
```

## Links with the other plugins

- **GRC Manager** ([glpi-iso27001-management](https://github.com/parime/glpi-iso27001-management)):
  evidence for controls A.5.9 (inventory), A.8.8 (technical vulnerabilities), A.8.9 (configuration)
  and A.8.32 (changes); Harbor image vulnerabilities in the vulnerability correlation.
- **Configuration GLPI Auto** ([Configuration-glpi-auto](https://github.com/parime/Configuration-glpi-auto)):
  wizard step to declare the Portainer, WUD and Harbor connections.

## Documentation

- [ARCHITECTURE.md](ARCHITECTURE.md): tree, classes, tests
- [ROADMAP.md](ROADMAP.md): lots and issues
- [CHANGELOG.md](CHANGELOG.md)

## License

GPL-3.0-or-later, see [LICENSE](LICENSE).
