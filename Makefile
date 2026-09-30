.PHONY: up down demo demo-logs demo-down install update

up:
	docker compose up --build -d

down:
	docker compose down

demo:
	@test -f .env || touch .env
	@grep -q '^APP_KEY=base64:' .env || echo "APP_KEY=base64:$$(openssl rand -base64 32)" >> .env
	docker compose -f docker-compose.yaml -f docker-compose.local.yaml up --build -d

demo-logs:
	docker compose -f docker-compose.yaml -f docker-compose.local.yaml logs -f

demo-down:
	docker compose -f docker-compose.yaml -f docker-compose.local.yaml down

install:
	cd main && npm install
	cd admin && composer install && npm install
	@test -f admin/.env || cp admin/.env.example admin/.env
	@test -f main/.env || (test -f main/.env.example && cp main/.env.example main/.env || true)

update:
	cd main && npx --yes npm-check-updates -u && npm install
	cd admin && composer update --with-all-dependencies --no-interaction && npx --yes npm-check-updates -u && npm install
