# syntax=docker/dockerfile:1

# Production image for packages/mcp running its HTTP transport (--http). The
# stdio transport needs no image at all — a local client spawns the process
# itself — so this exists only for the remote case: a public URL claude.ai can
# add as a connector.
#
# Same two-stage shape as docker/gallery/Dockerfile, and for the same reason:
# pnpm's node_modules is symlinks into a store at the workspace root, so the
# runtime stage installs again rather than copying a tree of dangling links.

# ---------------------------------------------------------------- build
FROM node:24-alpine AS build

ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
WORKDIR /build

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
COPY packages/mcp/package.json packages/mcp/package.json
RUN corepack enable && pnpm install --frozen-lockfile --filter @studio/mcp...

# The build's prebuild step generates src/generated/openapi.d.ts from this.
COPY packages/contracts packages/contracts
COPY packages/mcp packages/mcp
RUN pnpm --filter @studio/mcp build

# ---------------------------------------------------------------- runtime
FROM node:24-alpine AS mcp

ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
WORKDIR /app

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
COPY packages/mcp/package.json packages/mcp/package.json
RUN corepack enable && pnpm install --frozen-lockfile --prod --filter @studio/mcp...

COPY --from=build /build/packages/mcp/dist packages/mcp/dist

# Baked, like docker/gallery/Dockerfile's: the release is a property of the
# image, and src/observability.ts reads it once at process start.
ARG SENTRY_RELEASE=""

ENV PORT=3000 \
    NODE_ENV=production \
    MCP_TRANSPORT=http \
    SENTRY_ENVIRONMENT=production \
    SENTRY_RELEASE=$SENTRY_RELEASE
EXPOSE 3000

USER node

# The one route that answers without a bearer token, which is exactly what
# makes it the honest probe: reaching it proves the listener is up, and it is
# the first thing a remote client fetches anyway.
HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=6 \
    CMD node -e "fetch('http://127.0.0.1:3000/.well-known/oauth-protected-resource').then(r=>process.exit(r.ok?0:1),()=>process.exit(1))"

CMD ["node", "packages/mcp/dist/index.js"]
