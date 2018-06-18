#Unifresh SMS Page

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

SQL commands for future db change/updates
```
update or insert into templates (id, name, message) values((select coalesce(max(id),0)+1 from templates), 'ppp4', 'pok444kk') matching(name);
create unique index customer_index on customermaster(customer);
create index sales_index on salesheader(orderdate);
```
