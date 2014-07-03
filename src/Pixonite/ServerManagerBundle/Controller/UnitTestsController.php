==SPEC FOR FUTURE UNIT TEST==

iptables test:
  nmap -p 0-65535 -PN 71.19.151.44


syncSystemClock
  date +"%m-%d-%y %H-%M %z"

disk_mkdisk
  df -h
  >> CHECK AGAINST TEMPLATE

- check that fail2ban is running

- check for compile errors

- check for word 'Copyright' on index page

- check if anything is outputted: systemctl --state=failed

- check logs for:
	emerge: there are no ebuilds to satisfy "dhcpd".

- check logs for:
	cannot create executables
