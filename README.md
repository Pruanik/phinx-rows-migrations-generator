# Phinx migrations generator for data in tables

Generate a migration by comparing your current database to your mapping information.

This fork of the main project ([Phinx-Migrations-Generator](https://github.com/odan/phinx-migrations-generator)) synchronizes the data in the given project tables. This could be useful for transferring data from system tables or settings stored in the database.

[Phinx](https://phinx.org/) cannot automatically generate migrations.
Phinx creates "only" a class with empty `up`, `down` or `change` functions. You still have to write the migration manually.

In reality, you should rarely have to write migrations manually because the migration library should automatically generate migration classes by comparing your schema mapping information (i.e. how your database should look like) with your current database structure.

## Install

Via Composer

```
$ composer require pruanik/phinx-rows-migrations-generator --dev
```

## Usage

### Generating migrations

On the first run, an inital schema and a migration class is generated.
The `schema.php` file contains the previous database schema and is getting compared with the current schema.
Based on the difference, a Phinx migration class is generated.

```
$ vendor/bin/phinx-rows-migrations generate --overwrite
```

By executing the `generate` command again, only the difference to the last schema is generated.

## Parameters

Parameter | Values | Default | Description
--- | --- | --- | ---
--name | string | | The class name.
--overwrite | bool |  | Overwrite schema.php file.
--path <path> | string | (from phinx) | Specify the path in which to generate this migration.
--environment or -e | string | (from phinx) | The target environment.

### Running migrations

The [Phinx migrate command](http://docs.phinx.org/en/latest/commands.html#the-migrate-command) runs all of the available migrations.

```
$ vendor/bin/phinx migrate
```

## Configuration

The phinx-migrations-generator uses the configuration of phinx.

## Migration configuration

Parameter | Values | Default | Description
--- | --- | --- | ---
foreign_keys | bool | false | Enable or disable foreign key migrations.

### Example configuration

You can find example config in repo: tests/phinx.php.example

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
