FROM postgres:16-alpine

ENV POSTGRES_PASSWORD docker
ENV POSTGRES_DB db
ENV POSTGRES_USER docker

COPY ./database /docker-entrypoint-initdb.d