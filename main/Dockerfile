# Production: deps + Svelte build in image (no host deps).
FROM node:alpine

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .
RUN npm run build:prod

EXPOSE 80

ENV PORT=80
ENV HOST=0.0.0.0
CMD ["node", "build/index.js"]
