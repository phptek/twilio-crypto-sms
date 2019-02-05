#!/bin/bash
#
# Simple reference script for a quick build --> fix --> up process for docker-compose with dedicated TCP port
# Note, if running with sudo, pass the -E flag to preserve environment variables, namely DOCKER_HOST that is set above.
# e.g
# $> sudo -E docker-compose build or $> sudo -E docker-compose up

# stop default docker service running after install
sudo systemctl stop docker

# restart it, running on a TCP port instead of the default Unix socket
sudo /usr/bin/dockerd -H 127.0.0.1:2375

# tell all subsequent Docker process where to find the new Docker host
export DOCKER_HOST=tcp://127.0.0.1:2375
docker-compose build && docker-compose up

