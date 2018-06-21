# Unifresh SMS Page

*PHP program to send sms to customers whose order is not recieved.*

*Steps to run on Ubuntu*
Install Firebird db
```
sudo apt install firebird3.0-server
sudo apt install firebird-dev
```

Connect to db like this
```
isql-fb -u sysdba -p masterkey kunwardb.fdb
```

Install PHP Interbase library
```
sudo apt-get install php-interbase
```

Install PHP cURL library
```
sudo apt-get install php-curl
```

Saving SQL commands here for future db change/updates
```
create table templates(id integer primary key, name varchar(50), message varchar(500));
create unique index customer_index on customermaster(customer);
create index sales_index on salesheader(orderdate);
```

End
