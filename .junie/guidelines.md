# Project Guidelines: Drupal (Droopler) Development

This project is a Drupal-based website using the **Droopler** distribution and a custom theme `droopler_a15`. It is containerized using Docker.

## 1. Build/Configuration Instructions

### Prerequisites
- **Docker** and **Docker Compose**.
- A Docker network named `npm` (used for Nginx Proxy Manager integration).

### Initial Setup
1. **Create the required network**:
   ```bash
   docker network create npm
   ```
2. **Environment Variables**:
   Copy `local.env` or create a `.env` file based on the project requirements. The project currently expects:
   - `DRUPAL_DB_HOST=mysql`
   - `DRUPAL_DB_USER=root`
   - `DRUPAL_DB_PASSWORD=root`
   - `DRUPAL_DB_DATABASE=a15-drupal`

3. **Build and Start Services**:
   ```bash
   docker-compose up -d --build
   ```

4. **Install Dependencies**:
   Dependencies are managed via Composer. To install them inside the container:
   ```bash
   docker-compose exec drupal composer install
   ```

### Project Structure
- `/web`: Drupal root.
- `/web/themes/custom/droopler_a15`: Custom theme directory.
- `/config/sync`: Drupal configuration files for synchronization.
- `/drush`: Drush configuration and custom commands.

---

## 2. Testing Information

### Configuration
The project uses **PHPUnit** for testing. Since the production Docker image is built with `--no-dev`, development dependencies must be installed manually for testing.

### Running Tests
To run tests, you must first ensure development dependencies are installed:

1. **Install dev dependencies**:
   ```bash
   docker-compose exec drupal composer install
   ```

2. **Execute PHPUnit**:
   You can run tests using the vendor binary:
   ```bash
   docker-compose exec drupal ./vendor/bin/phpunit tests/
   ```

### Adding New Tests
1. Create a new test file in the `tests/` directory (e.g., `tests/MyTest.php`).
2. Ensure the class extends `PHPUnit\Framework\TestCase`.
3. Example of a simple test:
   ```php
   <?php
   use PHPUnit\Framework\TestCase;

   class SimpleTest extends TestCase {
       public function testExample() {
           $this->assertTrue(true);
       }
   }
   ```

### Verified Test Example
A simple test was created and verified to run successfully inside the Drupal container:
```bash
docker run --rm -v $(pwd)/tests:/opt/drupal/tests a15-drupal-drupal bash -c "composer install && ./vendor/bin/phpunit tests/SimpleTest.php"
```

---

## 3. Additional Development Information

### Drush Usage
Drush is available inside the container. Use it for common Drupal tasks:
- **Clear Cache**: `docker-compose exec drupal vendor/bin/drush cr`
- **Import Config**: `docker-compose exec drupal vendor/bin/drush cim`
- **Status**: `docker-compose exec drupal vendor/bin/drush status`

### Theme Development (droopler_a15)
The custom theme uses **Gulp** for asset compilation (SASS, JS).
- Navigate to: `web/themes/custom/droopler_a15`
- Install Node dependencies: `npm install`
- Run Gulp: `gulp` or `npm run build`

### Code Style
- Follow **Drupal Coding Standards**.
- Use `drupal/coder` (PHP_CodeSniffer) for linting:
  ```bash
  docker-compose exec drupal ./vendor/bin/phpcs --standard=Drupal web/modules/custom
  ```

### Deployment
The project includes a `deploy.php` file for use with **Deployer**.

### Module Development
When developing modules, ensure they follow Drupal's best practices and coding standards. Use the `web/modules/custom` directory for your custom modules. Test modules thoroughly before committing changes.

#### Modules
##### Booking module (Renting out the clubhouse)
- The view should show a Calendar build with FullCalendar https://github.com/fullcalendar/fullcalendar
- Free slots en booked slots should be highlighted
- Admins users should be able to create free slots
- Guests should be able to book free slots and request custom slots (with a form)
- Bookings should have a status field with (requested, reserved, booked). Reserved and booked slots should be visible to the public in the calendar.
- Bookings are always a rang of dates (from and to) without time.
- They should be able to cancel their booking and receice a confirmation email
- The booking should be saved in the database
- The booking should be removed from the database when the user cancels the booking
- The booking module should be used in the platform as a paragraph type
- Module should be translated to Dutch.
- Free and booked slots should be manageable in the backend in a table. (Able to create delete and edit)
- Backend table should be default filtered by upcoming dates ascending.
  - By default only show booked slots.
  - filters options are available on the backend table.
- Slots should be requested and created by day without time.
- Admin functionality should be avaiable from backend menu under content.
- A separate paragraph block with a list showing all free slots in the future grouped by month.
