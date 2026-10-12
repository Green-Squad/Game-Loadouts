FROM mysql:5.7.44@sha256:4bc6bc963e6d8443453676cae56536f4b8156d78bae03c0145cbe47c2aad73bb
COPY hand-forged/seed.sql.gz /opt/hand-forged-seed.sql.gz
COPY hand-forged/mysql-options.cnf /etc/mysql/conf.d/hand-forged.cnf
COPY hand-forged/build-snapshot.sh /opt/build-snapshot.sh
RUN sh /opt/build-snapshot.sh && rm /opt/hand-forged-seed.sql.gz
COPY hand-forged/database-start.sh /opt/database-start.sh
ENTRYPOINT ["sh","/opt/database-start.sh"]
