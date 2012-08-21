#!/bin/bash

if [ ! -f data/schema.sql ]
then
  echo "CRITICAL: file ./data/schema.sql can't be found - execute initialize.sh"
  echo "from the CAD distribution/installation directory."
  echo "Exiting."
  exit
elif [ ! -r data/schema.sql ]
then
  echo "CRITICAL: file ./data/schema.sql can't be read - check permissions."
  echo "Exiting."
  exit
fi

OC_VERSION=`grep OC_VERSION VERSION | cut -d \" -f 2`
OC_LEVEL=`grep OC_LEVEL VERSION | cut -d \" -f 2`
OC_RELEASE_DATE=`grep OC_RELEASE_DATE VERSION | cut -d \" -f 2`

echo "Black Flower CAD $OC_VERSION$OC_LEVEL - $OC_RELEASE_DATE"
echo "initialize.sh - Initializing database and config file"

defdbname="cad"
defdbhost="localhost"
defapphost="localhost"
defdbuser="cad"
defdbpass="default-password"
defcaduser="Administrator"
defcadpass="default-admin-pw"
defcadpasshash='$2a$08$En93eo6qk7f1Ph/S6gr0V.9PCzorokzhsQsLQBZrQCnE9eMlsSIBe'

echo
if [ ! -r cad.conf.example ]
then
  echo "CRITICAL: cad.conf.example can't be found to initialize cad.conf."
  echo "Exiting."
  exit
elif [ -r cad.conf ]
then
  if [ x$1x == "x--overwrite-configx" ]
  then
    echo "WARNING: Will overwrite cad.conf from cad.conf.example."
  elif [ x$1x == "x--preserve-configx" ]
  then
    echo "Preserving cad.conf settings."
    defdbname=`grep "DB_NAME = " cad.conf | cut -d \" -f 2`
    defdbhost=`grep "DB_HOST = " cad.conf | cut -d \" -f 2`
    defdbuser=`grep "DB_USER = " cad.conf | cut -d \" -f 2`
    defdbpass=`grep "DB_PASS = " cad.conf | cut -d \" -f 2`
    defcaduser=`grep -E "DEFAULT_ADMIN\s*=" cad.conf | cut -d \" -f 2`
  else
    echo "CRITICAL: cad.conf already exists."
    echo "In order to run initialization again, call this script as either:"
    echo "  initialize.sh --preserve-config     (to preserve any existing values)"
    echo "  initialize.sh --overwrite-config    (to overwrite existing cad.conf)"
    echo
    echo "Exiting."
    exit
  fi
fi

echo

echo "[Define MySQL parameters]"
read -p "  Enter name of host to use for MySQL database (default: $defdbhost): " dbhost
read -p "  Name of host to allow apps to connect to MySQL (default: $defapphost): " apphost
read -s -p "  Enter MySQL admin (root) user password (will not echo): " mysqlrootpw
echo
read -p "  Enter database name to create for CAD (default: '$defdbname'): " dbname

echo
echo "[Create a MySQL account for the CAD application to use]"
echo "  This account will only be used between the CAD scripts and the database."
echo
read -p "  Enter MySQL username (default: '$defdbuser'): " dbuser
read -p "  Enter new password for this user (default: '$defdbpass'): " dbpass 

echo
echo "[Create a CAD administrator account]"
echo "  Record this information and keep it secure.  This account will be used to"
echo "  log into CAD to perform administrative tasks such as adding/deleting users."
echo ""
echo "  The initial password will be \"$defcadpass\" and you'll be prompted to"
echo "  change it when first logging in."
echo
read -p "  Enter CAD administrator username (default: '$defcaduser'): " caduser

echo
echo "******************************"
echo



if [ x${dbname}x == xx ]
then
        dbname=$defdbname
fi
if [ x${apphost}x == xx ]
then
        apphost=$defapphost
fi
if [ x${dbhost}x == xx ]
then
        dbhost=$defdbhost
fi
if [ x${dbpass}x == xx ]
then
        dbpass=$defdbpass
fi
if [ x${caduser}x == xx ]
then
        caduser=$defcaduser
fi


# TODO: detect if database already exists
echo
echo -n "Creating database $dbname on $dbhost... "
mysqladmin -u root --password=$mysqlrootpw -h $dbhost create $dbname
e1=$?
if [ $e1 -ne 0 ]
then
  echo "Error $e1 returned from 'mysqladmin' when creating database; exiting."
  exit
fi
echo "done."

# TODO: detect if schema already exists
echo -n "Loading data/schema.sql... "
mysql -u root --password=$mysqlrootpw -h $dbhost $dbname < data/schema.sql
e1=$?
if [ $e1 -ne 0 ]
then
  echo "Error $e1 returned from 'mysql' when loading schema.sql; exiting."
  exit
fi
echo "done."


if [ x${dbuser}x == xx ]
then
        dbuser=cad
fi

# TODO: detect if user already exists
echo -n "Creating archive database... "
echo "create database if not exists cadarchives" | mysql -u root --password=$mysqlrootpw -h $dbhost
e1=$?
if [ $e1 -ne 0 ]
then
  echo "Error $e1 returned from 'mysql' when creating user $dbuser; exiting."
  exit
fi
echo "done."

# TODO: detect if user already exists
echo -n "Creating MySQL account... "
echo "grant all privileges on $dbname.* to '$dbuser'@'$apphost' identified by '$dbpass'" | mysql -u root --password=$mysqlrootpw -h $dbhost
e1=$?
if [ $e1 -ne 0 ]
then
  echo "Error $e1 returned from 'mysql' when creating user $dbuser; exiting."
  exit
fi
echo "grant all privileges on cadarchives.* to '$dbuser'@'$apphost'" | mysql -u root --password=$mysqlrootpw -h $dbhost
e1=$?
if [ $e1 -ne 0 ]
then
  echo "Error $e1 returned from 'mysql' when creating user $dbuser; exiting."
  exit
fi
echo "done."

if [ "x${1}x" != "x--preserve-configx" ]
then
  cp cad.conf.example cad.conf
fi

echo -n "Setting config file value for CAD MySQL DB username..."
perl -pi -e "s/(DB_USER\s*=\s*)\"(.*)\"/\$1\"$dbuser\"/" cad.conf
echo "done."

echo -n "Setting config file value for CAD MySQL DB password..."
perl -pi -e "s/(DB_PASS\s*=\s*)\"(.*)\"/\$1\"$dbpass\"/" cad.conf
echo "done."

echo -n "Setting config file value for CAD MySQL DB hostname..."
perl -pi -e "s/(DB_HOST\s*=\s*)\"(.*)\"/\$1\"$dbhost\"/" cad.conf
echo "done."

echo -n "Setting config file value for CAD MySQL DB name..."
perl -pi -e "s/(DB_NAME\s*=\s*)\"(.*)\"/\$1\"$dbname\"/" cad.conf
echo "done."

# TODO: grant privileges for paging if using paging link

# TODO: detect if Administrator user already exists
echo -n "Creating CAD administrator account... "
echo "INSERT INTO users (username, password, name, access_level, change_password) VALUES ('$caduser', '$defcadpasshash', 'Administrator Account', 15, 1)" | mysql -u $dbuser --password=$dbpass -h $dbhost $dbname
e1=$?
if [ $e1 -ne 0 ]
then
  echo "Error $e1 returned from 'mysql' when creating CAD adminstrator user; exiting."
  exit
fi
echo "done."

echo -n "Setting config file value for CAD administrator username..."
perl -pi -e "s/(DEFAULT_ADMIN\s*=\s*)\"(.*)\"/\$1\"$caduser\"/" cad.conf
echo "done."

echo -n "Setting file permissions... "
chmod 755 . Images Logos font font/makefont js
chmod 644 .htaccess cad.conf session.inc favicon.ico VERSION Images/* Logos/*
find . -name "*.php" -exec chmod 644 {} \;
find . -name "*.css" -exec chmod 644 {} \;
find . -name "*.js" -exec chmod 644 {} \;
find . -name "*.map" -exec chmod 644 {} \;
echo "done."

### If mysql password was empty, advise them to change it
if [ "x${mysqlrootpw}x" == "xx" ]
then
  echo
  echo "NOTICE:  Initialization was performed with a blank MySQL root password."
  echo " This is highly insecure.  Change it at your earliest convenience."
  echo " (See MySQL documentation for further details.)"
fi

echo
echo "CAD initialization completed."


