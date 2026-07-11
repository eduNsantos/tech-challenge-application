# Kubernetes quickstart (postech)

Este guia mostra como subir a stack no Kubernetes local (ex.: Minikube) e como configurar o `imagePullSecrets` para baixar imagem privada do GHCR.

## 1. Pre-requisitos

- `kubectl` instalado e apontando para o cluster correto
- Cluster Kubernetes ativo (ex.: Minikube)
- Namespace `postech`
- Imagem publicada no GHCR (ex.: `ghcr.io/edunsantos/tech-challenge:latest`)

## 2. Subir infraestrutura base

Na raiz do projeto:

```bash
kubectl apply -f k8s/00-namespaces/
kubectl apply -f k8s/01-config/
kubectl apply -f k8s/02-app/mysql-pv.yaml
kubectl apply -f k8s/02-app/mysql-pvc.yaml
kubectl apply -f k8s/02-app/mysql-deployment.yaml
kubectl apply -f k8s/02-app/mysql-service.yaml
```

## 3. Criar secrets da aplicacao

Se ainda nao existirem:

```bash
kubectl create secret generic app-secret \
  -n postech \
  --from-literal=APP_KEY="base64:CHANGE_ME" \
  --from-literal=DB_PASSWORD="CHANGE_ME"
```

Ajuste os valores conforme seu ambiente.

## 4. Criar secret para pull de imagem no GHCR

Se o pacote no GHCR for privado, crie um token (PAT) com escopo `read:packages` e rode:

```bash
kubectl create secret docker-registry ghcr-secret \
  -n postech \
  --docker-server=ghcr.io \
  --docker-username=SEU_USUARIO_GITHUB \
  --docker-password=SEU_PAT \
  --docker-email=SEU_EMAIL
```

Importante:
- Nao commitar PAT em arquivos do repositorio.
- Se o pacote for publico, esse secret pode nao ser necessario.

## 5. Aplicar app e swagger

```bash
kubectl apply -f k8s/02-app/app-deployment.yaml
kubectl apply -f k8s/02-app/app-service.yaml
kubectl apply -f k8s/02-app/app-hpa.yaml
kubectl apply -f k8s/02-app/swagger-deployment.yaml
kubectl apply -f k8s/02-app/swagger-service.yaml
```

## 6. Rodar migracoes

Job tem `spec.template` imutavel. Sempre recrie quando mudar imagem/comando/env:

```bash
kubectl delete job app-migrate -n postech --ignore-not-found
kubectl apply -f k8s/02-app/migrate-job.yaml
```

## 7. Validacao

```bash
kubectl get pods -n postech
kubectl get svc -n postech
kubectl logs -n postech job/app-migrate
```

## 8. Troubleshooting rapido

- `ErrImageNeverPull`: `imagePullPolicy` esta `Never`. Troque para `IfNotPresent` ou `Always`.
- `ErrImagePull` + `manifest unknown`: nome/tag da imagem nao existe no GHCR.
- `ImagePullBackOff`: geralmente erro de autenticacao no GHCR ou tag inexistente.
- Job invalido com `field is immutable`: delete e recrie o Job.

## 9. Referencia de manifests

- App deployment: `k8s/02-app/app-deployment.yaml`
- Migrate job: `k8s/02-app/migrate-job.yaml`
- Deploy script: `k8s/deploy.sh`
