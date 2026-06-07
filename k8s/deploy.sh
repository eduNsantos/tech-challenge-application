#!/bin/bash
set -e

NAMESPACE="postech"
IMAGE="edunsantos/postech-app:latest"

echo "==> Aplicando namespace..."
kubectl apply -f k8s/namespace.yaml

echo "==> Aplicando ConfigMap..."
kubectl apply -f k8s/configmap.yaml

echo "==> Aplicando Secrets..."
kubectl apply -f k8s/secret.yaml

echo "==> Criando ConfigMap da spec OpenAPI..."
kubectl create configmap openapi-spec \
  --from-file=openapi.yaml \
  -n "$NAMESPACE" \
  --dry-run=client -o yaml | kubectl apply -f -

echo "==> Aplicando PVC do MySQL..."
kubectl apply -f k8s/mysql-pvc.yaml

echo "==> Aplicando MySQL..."
kubectl apply -f k8s/mysql-deployment.yaml
kubectl apply -f k8s/mysql-service.yaml

echo "==> Aguardando MySQL ficar pronto..."
kubectl rollout status deployment/mysql -n "$NAMESPACE" --timeout=120s

echo "==> Executando migrations..."
kubectl delete job app-migrate -n "$NAMESPACE" --ignore-not-found
kubectl apply -f k8s/migrate-job.yaml
kubectl wait --for=condition=complete job/app-migrate -n "$NAMESPACE" --timeout=120s

echo "==> Aplicando App..."
kubectl apply -f k8s/app-deployment.yaml
kubectl apply -f k8s/app-service.yaml
kubectl apply -f k8s/app-hpa.yaml

echo "==> Aplicando Swagger UI..."
kubectl apply -f k8s/swagger-deployment.yaml
kubectl apply -f k8s/swagger-service.yaml

echo "==> Aguardando App ficar pronto..."
kubectl rollout status deployment/postech-app -n "$NAMESPACE" --timeout=120s

echo ""
echo "Deploy concluido!"
echo ""
kubectl get pods -n "$NAMESPACE"
echo ""
kubectl get services -n "$NAMESPACE"
