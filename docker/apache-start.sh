#!/bin/sh
set -eu

# Enforce a single MPM at runtime (prefork) for mod_php compatibility.
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf
rm -f /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf

if [ ! -e /etc/apache2/mods-enabled/mpm_prefork.load ]; then
    ln -s ../mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
fi

if [ ! -e /etc/apache2/mods-enabled/mpm_prefork.conf ]; then
    ln -s ../mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
fi

exec apache2-foreground
