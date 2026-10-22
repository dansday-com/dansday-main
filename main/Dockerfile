# ── Stage 1: Build ──────────────────────────────────────────────
FROM node:26.12.1-alpine AS builder

WORKDIR /app

ENV NODE_OPTIONS=""

COPY package.json package-lock.json ./
RUN npm ci --include=dev

COPY . .
RUN npm run build

RUN npm prune --omit=dev

# ── Stage 2: Runtime ─────────────────────────────────────────────
FROM node:26.12.1-alpine AS runner

WORKDIR /app

RUN apk add --no-cache curl

COPY --from=builder /app/build ./build
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/package.json ./package.json
COPY --from=builder /app/otel ./otel

EXPOSE 1111

ENV HOST=0.0.0.0
ENV PORT=1111
ENV NODE_ENV=production

CMD ["node", "--import", "./otel/console-instrumentation.js", "build/index.js"]
