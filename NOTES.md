## Jack's Notes
### Preparation
I noticed that some AI-integration bits for Laravel aren't in the repo so I've written an AGENTS.md for the repository
to enforce standards and keep things consistent across multiple agents.

I've also added `laravel/boost` for MCP with PHPStorm and used `laravel/pao` to keep my token usage nice and clean.

Project is using PHP 8.4 but my system version is 8.5. Considered adding a docker-compose.yml to get the exact version
but seems excessive considering there's no DB server or queue.
