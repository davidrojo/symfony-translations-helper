# symfony-translations-helper
Simple commands to manage translations in Symfony.

This bundle allows you to write missing translations from a yml file to another requesting the missing translations and storing them in the output file.

For each non found translation it will request the translation showing the field id and the original text to translate, making it very easy to update your translation files whe you have added fields to them


## Insalation

Just require the bundle with composer:

`composer.phar require davidrojo/symfony-translations-helper`

And then add it to app/AppKernel.php:

```php
public function registerBundles()
    {
        $bundles = [
            ...
            new DavidRojo\SfTranslationsHelperBundle(),
            ...
        ]
    }
}
```


## Usage

Execute the command `php bin/console translations:helper from_language to_language BundleName|File allowEmpty`

Parameters:

- from_language: The origin language
- to_language: The destination language
- BundleName|File: The bundle we want to translate files if it has a Resources/translations/messages.[LANG].yml, or it can be a realtive path to a yml file wit filename ending in ".[LANG].yml"
- allowEmpty (true/false): Default true, if we dont't add a translation when promped if this parameter is true, the entry will be created empty, else it will not be created.

## Example

```
$ php bin/console translations:helper en fr AppBundle

Destination file already exists. Override? (y/n): y
Counting missing translations...3 missing, 0 empty

Field id: delete
Original: Eliminar
Please enter the translation: Effacer

Field id: courses.create
Original: Crear curso
Please enter the translation: Créer un cours

Field id: total
Original: Total
Please enter the translation: Total
Saving file.
File saved at /AppBundle//Resources/translations/messages.fr.yml
```

## Contributions

Just open an issue or send a pull request and if it fits it will be integrated.

## Donations

If you liked this bundle and saved you a little of time, you can:

- [buy me a coffe](https://www.paypal.me/DavidRojoGonzalez/2)
- [buy me a dinner](https://www.paypal.me/DavidRojoGonzalez/10)
- [buy me a good dinner](https://www.paypal.me/DavidRojoGonzalez/30)
- [buy me a phpstorm license](https://www.paypal.me/DavidRojoGonzalez/89)
- [pay my rent](https://www.paypal.me/DavidRojoGonzalez/650)
- [buy me a car](https://www.paypal.me/DavidRojoGonzalez/12000)
- [buy me a super car](https://www.paypal.me/DavidRojoGonzalez/150000)

;)
