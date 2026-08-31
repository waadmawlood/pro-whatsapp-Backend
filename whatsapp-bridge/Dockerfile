FROM node:20-alpine

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm install --omit=dev

COPY src ./src

ENV PORT=3001
ENV SESSIONS_DIR=/app/sessions

VOLUME ["/app/sessions"]

EXPOSE 3001

CMD ["npm", "start"]
