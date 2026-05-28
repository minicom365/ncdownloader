<?php

// 이 모듈은 아키텍처 명세서의 '사용자 행동 로깅 및 자원 관리' 단계를 구현하였으며, 대형 로그로 인한 디스크 고갈 예방을 위해 경량 자체 회전(Rotation) 방식을 적용하고 외부 로거 의존에 따른 병목을 배제함.

namespace OCA\NCDownloader\Middleware;

use Exception;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Response;
use OCP\AppFramework\Middleware;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

class ActionLogMiddleware extends Middleware
{
    private IRequest $request;
    private IUserSession $userSession;
    private IConfig $config;

    public function __construct(IRequest $request, IUserSession $userSession, IConfig $config)
    {
        $this->request = $request;
        $this->userSession = $userSession;
        $this->config = $config;
    }

    public function beforeController(Controller $controller, string $methodName): void
    {
        $entry = $this->baseEntry($controller, $methodName);
        $entry['event'] = 'start';
        $this->appendLog($entry);
    }

    public function afterController(Controller $controller, string $methodName, Response $response): Response
    {
        $entry = $this->baseEntry($controller, $methodName);
        $entry['event'] = 'success';
        $entry['status'] = $response->getStatus();
        $this->appendLog($entry);
        return $response;
    }

    public function afterException(Controller $controller, string $methodName, Exception $exception)
    {
        $entry = $this->baseEntry($controller, $methodName);
        $entry['event'] = 'error';
        $entry['exceptionClass'] = get_class($exception);
        $entry['exceptionMessage'] = $exception->getMessage();
        $this->appendLog($entry);
        throw $exception;
    }

    private function baseEntry(Controller $controller, string $methodName): array
    {
        $user = $this->userSession->getUser();

        return [
            'ts' => date('c'),
            'reqId' => $this->request->getId(),
            'user' => $user ? $user->getUID() : null,
            'method' => $this->request->getMethod(),
            'uri' => $this->request->getRequestUri(),
            'remoteAddr' => $this->request->getRemoteAddress(),
            'controller' => get_class($controller),
            'action' => $methodName,
            // Log only parameter names to avoid leaking sensitive values.
            'params' => array_keys($this->request->getParams()),
        ];
    }

    private function getLogFilePath(): string
    {
        $dataDir = rtrim($this->config->getSystemValueString('datadirectory', '/tmp'), '/');
        return $dataDir . '/ncdownloader/actions.log';
    }

    private function rotateLog(string $logFile): void
    {
        $maxBackups = 3;
        for ($i = $maxBackups - 1; $i >= 1; $i--) {
            $oldFile = $logFile . '.' . $i;
            $newFile = $logFile . '.' . ($i + 1);
            if (@file_exists($oldFile)) {
                @rename($oldFile, $newFile);
            }
        }
        if (@file_exists($logFile)) {
            @rename($logFile, $logFile . '.1');
        }
    }

    private function appendLog(array $entry): void
    {
        $logFile = $this->getLogFilePath();
        $logDir = dirname($logFile);

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0750, true);
        }

        if (@is_file($logFile) && @filesize($logFile) > 10 * 1024 * 1024) {
            $this->rotateLog($logFile);
        }

        @file_put_contents(
            $logFile,
            json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL,
            FILE_APPEND | LOCK_EX
        );
    }
}
