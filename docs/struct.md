## github.com/dbschemix/core
```
/src
├── command                  
│   ├── Command.php                                                                                                               
│   ├── CommandInterface.php                                                                                                                
│   └── Options.php 
├── connection
│   ├── mysql              
│   │   └── setup.sql
│   ├── pgsql             
│   │   └── setup.sql
│   ├── sqlite           
│   │   └── setup.sql
│   ├── ConnectionInterface.php
│   ├── DriverInterface.php
│   ├── StatementInterface.php
│   └── TransactionInterface.php
├── event                      
│   ├── EventAction.php       
│   ├── EventDispatcher.php
│   ├── EventInterface.php
│   ├── Event.php                                                                                                                           
│   ├── EventPublisherInterface.php
│   ├── EventSubscriberInterface.php
│   ├── ExceptionEvent.php
│   ├── MigrateErrorEvent.php
│   └── MigrateSuccessEvent.php
├── exception
│   ├── ActionException.php
│   ├── ConfigurationException.php
│   ├── ConnectionException.php
│   ├── InitializationException.php
│   ├── MigratorException.php
│   └── PrepareException.php
├── internal
│   ├── action
│   │   └── Workflow.php
│   └── filesystem
│       ├── Action.php
│       ├── ActionType.php
│       ├── functions.php
│       ├── Options.php
│       └── Setup.php
├── template
│   ├── FactoryInterface.php
│   └── Factory.php
├── Config.php
├── Context.php
├── InputOptions.php
├── Migration.php
├── MigratorInterface.php
└── Migrator.php
```

## github.com/dbschemix/pdo

Зависимость от:
- github.com/dbschemix/core
- ext-pdo

```
./src
├── internal
│    ├── Connection.php
│    ├── ErrorInfo.php
│    ├── FactoryTransaction.php
│    ├── Statement.php
│    ├── ThrowPrepareException.php
│    ├── TransactionMysql.php
│    └── Transaction.php
├── Driver.php
└── Type.php

```

