## Twilio Crypto SMS

### What is this?

A small SilverStripe application developed alongside a [Twilio.com](https://twilio.com) blog post. It encapsulates several different paradigms in one:

 * Web 2.0 to Web 3.0 integration with [Bitcoin](https://bitcoin.org) and [Ethereum](https://ethereum.org).
 * Twilio SMS integration
 * SilverStripe
 * Immutible, 3rd party verification

### Development

Development occurs using Docker and docker-compose. If you're new to Docker the whole ecosystem can be confusing if you've never encountered first-hand, a scenario where you might need it. For now, think of the Dockerfile as being roughly equivalent to a Vagrantfile (if you're familliar with those) where your SilverStripe app's requirements are defined. The docker-compose.yml file is used to describe how your SilverStripe app should be built in the context of its other "components" such as its database for example. For those familliar with microservices, you'd declare each discrete service (one service, maybe a Python/Django app and another PHP/Laravel app) where each would have its own Dockerfile. Your whole "app" comprises the Python and the PHP app, which you'd bring together and build using docker-compose.
