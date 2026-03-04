.PHONY: up down install update

up:
	docker compose up --build -d

down:
	docker compose down

install:
	docker compose run --rm -v $(PWD)/main:/app main npm install
	docker compose run --rm -v $(PWD)/main:/app main npm run build
	@test -f main/.env || cp main/.env.example main/.env
	docker compose run --rm -v $(PWD)/admin:/app admin sh -c "mkdir -p bootstrap/cache storage/framework/cache storage/framework/sessions storage/framework/views storage/logs && chmod -R 775 bootstrap/cache storage"
	docker compose run --rm -v $(PWD)/admin:/app admin composer install
	docker compose run --rm -v $(PWD)/admin:/app admin npm install
	docker compose run --rm -v $(PWD)/admin:/app admin sh -c "ln -snf ../assets public/assets"
	docker compose run --rm -v $(PWD)/admin:/app admin npm run build
	docker compose run --rm -v $(PWD)/admin:/app admin sh -c "test -f .env || cp .env.example .env; php artisan key:generate --no-interaction --force; php artisan storage:link"

update:
	docker compose run --rm -v $(PWD)/main:/app main sh -c "npx --yes npm-check-updates -u && npm install"
	docker compose run --rm -v $(PWD)/admin:/app admin sh -c "composer update --with-all-dependencies --no-interaction && npx --yes npm-check-updates -u && npm install"
