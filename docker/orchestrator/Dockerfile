# The execution orchestrator (ADR 0007).
#
# Holds container-creation privilege and nothing else. There are deliberately no
# database drivers, no cache client and no application code in this image: a
# component that cannot reach the database cannot leak it, however badly it is
# compromised.
FROM node:22-alpine

# The Docker CLI only. Not compose, not the SDK — this image speaks exactly one
# protocol and it is worth being able to see that from the package list.
RUN apk add --no-cache docker-cli

WORKDIR /srv

COPY docker/orchestrator/server.mjs ./server.mjs

# No npm install, because there are no dependencies. See the header of
# server.mjs: this is the component whose compromise matters most, so its whole
# supply chain should be Node's standard library.

EXPOSE 8090

CMD ["node", "server.mjs"]
