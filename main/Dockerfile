# ── Stage 1: Build ──────────────────────────────────────────────
FROM node:25.8.1-alpine AS builder

WORKDIR /app

ENV NODE_OPTIONS=""

COPY package.json ./
RUN npm install

COPY . .
RUN npm run build

RUN npm prune --production

# ── Stage 2: Runtime ─────────────────────────────────────────────
FROM node:25.8.1-alpine AS runner

WORKDIR /app

COPY --from=builder /app/build ./build
COPY --from=builder /app/node_modules ./node_modules
COPY --from=builder /app/package.json ./package.json
COPY --from=builder /app/otel ./otel

EXPOSE 3000

ENV HOST=0.0.0.0
ENV PORT=3000
ENV NODE_ENV=production

CMD ["node", "--import", "./otel/console-instrumentation.js", "build/index.js"]
