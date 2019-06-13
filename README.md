# LeNats
Event-driven NATS streaming Symfony 4 bundle with Cloud Event standard 

## Install
```
composer require lebrains/lentas
```

## Configuration
In `config/packages/le_nats.yaml`:
```yaml
le_nats:
  connection:
    dsn: 'tcp://your-nats/host' # Required.
    client_id: 'unique_app_client_id' # Required. A unique identifier for a client. See ConnectRequest in https://nats.io/documentation/streaming/nats-streaming-protocol/
    cluster_id: 'your_nats_cluster_id' # Required. Cluster ID from nats streaming configuration. See https://github.com/nats-io/nats-streaming-server#configuration-file
    verbose: false # Optional. by default false, if you need additional set it to true
     
    context: # Optional. if you need additional configuration for TLS or TCP (will added soon) - you can define it here
      tls:
        protocol: tlsv1.2
        ciphers: ECDHE-RSA-AES256-GCM-SHA384
        peer_name: 'connection_peer_name'
        verify_peer: false
        verify_peer_name: false
        allow_self_signed: true
        
  accept_events: # Optional.
    # If you want to accept specific Event Object for each event - you can define it here
    # Here application.test.created - event type (or name)
    # App\Events\TestCloudEvent - class for this event
    # Class App\Events\TestCloudEvent MUST inherit LeNats\Events\CloudEvent
    application.test.created: App\Events\TestCloudEvent
```

## Workflow
### Publishing
```php
<?php
$publisher = $container->get(\LeNats\Subscription\Publisher::class);

$event = new \LeNats\Events\CloudEvent();
$event->setType('some.event.type.created'); // This event wil be published to some.event.type queue
// $event->setId('unique-id'); Id will gets from data['id'] field, if it exists 
$data = [
    'id' => 'unique-id',
    'value' => 'Some event value'
];
$event->setData($data);

$publisher->publish($event);
```

### Subscribing
```
bin/console nats:subscribe some.event.type -t 10
```
Make sure the Listener of your Event Type exists

