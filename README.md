# Fly Copy

Copies a file from OpenStack to the local filesystem if it has been modified.

## Usage

Run from the command line!

Use `-e` or `--env` to define an environment file basename. This will be suffixed with `.env` and
loaded in at runtime. 

For example, to load in environment variables from `my.env` use:

```
php copy.php -e=my
```

Use `-?` or `--help` to print a usage statement:

```
php copy.php -?
```
