# Production: deps + Svelte build in image (no host deps).
FROM node:alpine

WORKDIR /app

# Install deps (include devDependencies for build; Coolify may set NODE_ENV=production)
COPY package.json package-lock.json* ./
RUN sh -c 'unset NODE_OPTIONS; npm config set fund false && \
    ( [ -f package-lock.json ] && npm ci --include=dev || npm install --include=dev )'

COPY . .
RUN npm run build:prod

EXPOSE 80

ENV PORT=80
ENV HOST=0.0.0.0
CMD ["node", "build/index.js"]
