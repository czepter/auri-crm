# syntax=docker/dockerfile:1

# Production image for apps/gallery: the Astro SSR server that renders a guest's
# gallery. Separate from docker/app/Dockerfile because it shares nothing with it
# — no PHP, no vendor, no surface switch — and because the two are deployed for
# different reasons: this one moves when a gallery page changes, that one when
# the studio does.
#
# Server output, not static: astro.config.mjs explains why a gallery page cannot
# be built once at deploy time. So `dist/` is a Node program, not a folder of
# HTML, and it needs a runtime here.

# ---------------------------------------------------------------- build
FROM node:24-alpine AS build

ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
WORKDIR /build

COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
COPY apps/gallery/package.json apps/gallery/package.json
RUN corepack enable && pnpm install --frozen-lockfile --filter @studio/gallery...

COPY apps/gallery apps/gallery
RUN pnpm --filter @studio/gallery build

# ---------------------------------------------------------------- runtime
FROM node:24-alpine AS gallery

ENV COREPACK_ENABLE_DOWNLOAD_PROMPT=0
WORKDIR /app

# A second, production-only install rather than copying node_modules across:
# pnpm's tree is symlinks into a store at the workspace root, so a copy of one
# package's node_modules arrives with dangling links. This resolves them again
# against the same lockfile, without the dev dependencies.
COPY package.json pnpm-lock.yaml pnpm-workspace.yaml ./
COPY apps/gallery/package.json apps/gallery/package.json
RUN corepack enable && pnpm install --frozen-lockfile --prod --filter @studio/gallery...

COPY --from=build /build/apps/gallery/dist apps/gallery/dist

# The standalone adapter's server reads both of these and serves the client
# assets itself, so nothing else has to sit in front of it. 0.0.0.0 because the
# default binds loopback, which inside a container means nothing can reach it.
# Baked rather than passed per deploy: the release is a property of the image,
# and src/lib/observability/sentry.ts reads it once at process start to tag
# every event. GlitchTip needs it to say which build an error came from, so it
# has to be the commit — the CI workflow supplies it.
ARG SENTRY_RELEASE=""

ENV HOST=0.0.0.0 \
    PORT=4321 \
    NODE_ENV=production \
    SENTRY_ENVIRONMENT=production \
    SENTRY_RELEASE=$SENTRY_RELEASE
EXPOSE 4321

USER node

# Any HTTP response means the SSR process is alive, which is all a health check
# can honestly assert here: the only route this app has is /g/:galleryId, so
# every probe path 404s and a status-checking probe would report a healthy
# server as down forever.
HEALTHCHECK --interval=10s --timeout=3s --start-period=20s --retries=6 \
    CMD node -e "fetch('http://127.0.0.1:4321/').then(()=>process.exit(0),()=>process.exit(1))"

CMD ["node", "apps/gallery/dist/server/entry.mjs"]
