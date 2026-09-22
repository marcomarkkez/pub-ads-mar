FROM node:20-alpine

WORKDIR /app

RUN apk add --no-cache bash

COPY package.json package-lock.json* ./
RUN npm ci

COPY . .

EXPOSE 4300

ENV CHOKIDAR_USEPOLLING=true
ENV WATCHPACK_POLLING=true

CMD ["npx", "ng", "serve", "--host", "0.0.0.0", "--port", "4300", "--poll", "2000", "--disable-host-check"]
