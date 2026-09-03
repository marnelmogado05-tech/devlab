# A DevLab sandbox runtime: PHP 8.4.
#
# This image runs code a stranger wrote. It contains the interpreter, the
# entrypoint, and nothing that would be worth stealing.
FROM php:8.4-cli-alpine

# The entrypoint reads its work from stdin. Nothing is mounted from the host at
# any point, so there is no path for a submission to reach anything outside the
# container even before the isolation flags are applied.
COPY docker/sandbox/php/run.php /srv/run.php

# Owned by root and world-readable, so the unprivileged user the orchestrator
# runs as can execute it and cannot modify it.
RUN chmod 0444 /srv/run.php

# A default, not a defence: the orchestrator passes --user explicitly. Both are
# here because an image that is only safe when invoked correctly is an image
# waiting to be invoked incorrectly.
USER 65534:65534

ENTRYPOINT ["php", "/srv/run.php"]
