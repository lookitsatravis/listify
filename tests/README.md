# So, testing...

The issue here is that `Listify` is implemented via a PHP Trait. It's possible to test Traits straight out of the box with PHPUnit, but the problem here is that this trait is absolutley dependent on the Laravel stack, specifically Eloquent models. So, in order to test, I've got a full app present and the tests reside and run from there. Fun!

To contribute, you'll need to run `composer update` inside the `tests/test_app` directory so that the vendor directory is created.

If anyone has another thought on how to approach this, I would be happy to hear it.

Thanks!

Travis Vignon