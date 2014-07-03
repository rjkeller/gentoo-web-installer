<h1>Gentoo Site Manager</h1>

<p>Written by: R.J. Keller</p>

<p>A basic app to manage gentoo installations. I'm in the process of moving this to python and convert it into an installer (instead of a 'configuration manager').</p>

<h2>How to install</h2>

<p>These instructions may not be comprehensive. Ping me if you have problems.</p>

<pre>composer install
mysql -u [user] -p -D dbName < webapps.sql #if you want some samples
</pre>

<p>Note that the MySQL dump you're importing above was done using MariaDB 5.5.37.</p>

<h2>Some design notes</h2>

<p>Main GUI is managed using compass. The checkout automatically has compiled compass files in it. Feel free to do 'compass compile' in the compass directory to recompile. We don't use the compass extension that is part of assetic since it has some annoying limitations.</p>

<h2>License</h2>

<p>Apache v2</p>
