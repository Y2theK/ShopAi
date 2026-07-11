FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .

# Vite bakes VITE_* variables into the bundle at build time. Relative URLs
# work because nginx serves the SPA and the API from the same origin.
ARG VITE_API_BASE_URL=/api/v1
ARG VITE_BACKEND_URL=
ENV VITE_API_BASE_URL=${VITE_API_BASE_URL} \
    VITE_BACKEND_URL=${VITE_BACKEND_URL}

RUN npm run build

# ---- production image: nginx serving the SPA and proxying the API ----
FROM nginx:alpine

COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=build /app/dist /usr/share/nginx/html

EXPOSE 80
