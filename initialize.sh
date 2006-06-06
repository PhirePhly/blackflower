#!/bin/sh

echo "[Initialize MySQL CAD database]"
RES_1=`mysql -v -u root < schema.sql`;
exit

read -p "  Name of database to create and use (default: 'cad'): " dbname
stty -echo
read -p "  Current MySQL admin (root) password (will not echo): " mysqlroot; echo
stty echo
if [ x${dbname}x == xx ]
then
        dbname=cad
fi

echo
echo "[Create a MySQL account for the CAD system backend to use]"
echo "  This account will only be used between the CAD scripts and the database."
echo
read -p "  Enter MySQL username (default: 'cad'): " username
if [ x${username}x == xx ]
then
        username=cad
fi
stty -echo
read -p "  Enter new password (will not echo): " passw; echo
stty echo

echo
echo "[Create a CAD administrator account]"
echo "  Record this information and keep it secure.  This account should only"
echo "  be used to log into the CAD web interface to perform administrative"
echo "  tasks such as adding or deleting other CAD users.  If this information"
echo "  is lost, the password can be reset by the MySQL root user."
echo
echo    "  CAD administrator username will be: cadadmin"
stty -echo
read -p "  Enter new password (will not echo): " cadpassw; echo
stty echo

echo "Username is $username"
echo "Password is $passw"
echo "root is $mysqlroot"
echo "dbname is $dbname"


### If mysql password was empty, advise them to change it
