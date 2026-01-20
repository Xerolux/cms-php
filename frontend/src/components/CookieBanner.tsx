import React, { useEffect, useState } from 'react';
import { Button, Card, Col, Row, Space, Typography } from 'antd';
import { SafetyOutlined } from '@ant-design/icons';

const { Text, Paragraph, Title } = Typography;

interface ConsentSettings {
  necessary: boolean; // Immer true, kann nicht deaktiviert werden
  functional: boolean; // Funktionale Cookies
  analytics: boolean; // Analytics Cookies
  marketing: boolean; // Marketing Cookies
}

const CookieBanner: React.FC = () => {
  const [visible, setVisible] = useState(false);
  const [showSettings, setShowSettings] = useState(false);
  const [consent, setConsent] = useState<ConsentSettings>({
    necessary: true,
    functional: false,
    analytics: false,
    marketing: false,
  });

  useEffect(() => {
    // Prüfen ob Consent schon gegeben wurde
    const savedConsent = localStorage.getItem('cookie_consent');
    if (!savedConsent) {
      // Nur anzeigen wenn noch kein Consent
      setVisible(true);
    } else {
      setConsent(JSON.parse(savedConsent));
    }
  }, []);

  const handleAcceptAll = () => {
    const fullConsent: ConsentSettings = {
      necessary: true,
      functional: true,
      analytics: true,
      marketing: true,
    };
    setConsent(fullConsent);
    saveConsent(fullConsent);
    setVisible(false);
  };

  const handleAcceptNecessary = () => {
    const minimalConsent: ConsentSettings = {
      necessary: true,
      functional: false,
      analytics: false,
      marketing: false,
    };
    setConsent(minimalConsent);
    saveConsent(minimalConsent);
    setVisible(false);
  };

  const handleSaveSettings = () => {
    saveConsent(consent);
    setVisible(false);
    setShowSettings(false);
  };

  const saveConsent = (consentSettings: ConsentSettings) => {
    localStorage.setItem('cookie_consent', JSON.stringify(consentSettings));
    localStorage.setItem('cookie_consent_date', new Date().toISOString());

    // Hier könnten basierend auf Consent Scripts aktiviert/deaktiviert werden
    // z.B. Google Analytics nur laden wenn analytics = true

    // Example: Event dispatchen für andere Komponenten
    window.dispatchEvent(new CustomEvent('cookieConsent', { detail: consentSettings }));
  };

  if (!visible) return null;

  return (
    <div style={{
      position: 'fixed',
      bottom: 0,
      left: 0,
      right: 0,
      zIndex: 9999,
      padding: '24px',
      background: 'rgba(0, 0, 0, 0.5)',
    }}>
      <Card
        title={<Space><SafetyOutlined style={{ fontSize: 24 }} /> Cookie & Datenschutz-Einstellungen</Space>}
        style={{ maxWidth: 800, margin: '0 auto' }}
      >
        {!showSettings ? (
          <>
            <Paragraph>
              Wir verwenden Cookies, um Ihre Benutzererfahrung zu verbessern.
              Einige Cookies sind für die Grundfunktion der Website notwendig.
              Weitere Informationen finden Sie in unserer{' '}
              <a href="/privacy">Datenschutzerklärung</a>.
            </Paragraph>

            <Space style={{ marginTop: 16 }}>
              <Button type="primary" onClick={handleAcceptAll}>
                Alle akzeptieren
              </Button>
              <Button onClick={handleAcceptNecessary}>
                Nur notwendige
              </Button>
              <Button onClick={() => setShowSettings(true)}>
                Einstellungen anpassen
              </Button>
            </Space>
          </>
        ) : (
          <>
            <Title level={5}>Wählen Sie Ihre Cookie-Einstellungen:</Title>

            <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
              {/* Notwendige Cookies */}
              <Col span={24}>
                <Card size="small" style={{ background: '#f5f5f5' }}>
                  <Space direction="vertical" style={{ width: '100%' }}>
                    <Text strong>Notwendige Cookies (Immer aktiv)</Text>
                    <Text type="secondary">
                      Diese Cookies sind für die Grundfunktion der Website notwendig
                      und können nicht deaktiviert werden.
                    </Text>
                    <Switch
                      checked={consent.necessary}
                      disabled={true}
                      checkedChildren="An"
                      unCheckedChildren="Aus"
                      onChange={() => {}}
                    />
                  </Space>
                </Card>
              </Col>

              {/* Funktionale Cookies */}
              <Col span={24}>
                <Card size="small">
                  <Space direction="vertical" style={{ width: '100%' }}>
                    <Text strong>Funktionale Cookies</Text>
                    <Text type="secondary">
                      Diese Cookies ermöglichen erweiterte Funktionen wie
                      z.B. gespeicherte Einstellungen und Präferenzen.
                    </Text>
                    <Switch
                      checked={consent.functional}
                      onChange={(checked) =>
                        setConsent({ ...consent, functional: checked })
                      }
                      checkedChildren="An"
                      unCheckedChildren="Aus"
                    />
                  </Space>
                </Card>
              </Col>

              {/* Analytics Cookies */}
              <Col span={24}>
                <Card size="small">
                  <Space direction="vertical" style={{ width: '100%' }}>
                    <Text strong>Analytics Cookies</Text>
                    <Text type="secondary">
                      Diese Cookies helfen uns zu verstehen, wie Besucher
                      unsere Website nutzen, indem sie anonymisierte Daten sammeln.
                    </Text>
                    <Switch
                      checked={consent.analytics}
                      onChange={(checked) =>
                        setConsent({ ...consent, analytics: checked })
                      }
                      checkedChildren="An"
                      unCheckedChildren="Aus"
                    />
                  </Space>
                </Card>
              </Col>

              {/* Marketing Cookies */}
              <Col span={24}>
                <Card size="small">
                  <Space direction="vertical" style={{ width: '100%' }}>
                    <Text strong>Marketing Cookies</Text>
                    <Text type="secondary">
                      Diese Cookies werden verwendet um personalisierte Werbung
                      anzuzeigen und zu tracken.
                    </Text>
                    <Switch
                      checked={consent.marketing}
                      onChange={(checked) =>
                        setConsent({ ...consent, marketing: checked })
                      }
                      checkedChildren="An"
                      unCheckedChildren="Aus"
                    />
                  </Space>
                </Card>
              </Col>
            </Row>

            <Space style={{ marginTop: 24 }}>
              <Button type="primary" onClick={handleSaveSettings}>
                Einstellungen speichern
              </Button>
              <Button onClick={() => setShowSettings(false)}>
                Abbrechen
              </Button>
            </Space>
          </>
        )}
      </Card>
    </div>
  );
};

// Switch Komponente für Cookie Settings
const Switch: React.FC<{
  checked: boolean;
  disabled?: boolean;
  onChange: (checked: boolean) => void;
  checkedChildren: React.ReactNode;
  unCheckedChildren: React.ReactNode;
}> = ({ checked, disabled, onChange, checkedChildren, unCheckedChildren }) => {
  return (
    <button
      onClick={() => !disabled && onChange(!checked)}
      disabled={disabled}
      style={{
        position: 'relative',
        display: 'inline-block',
        width: 44,
        height: 22,
        padding: 0,
        background: disabled ? '#d9d9d9' : checked ? '#1890ff' : '#bfbfbf',
        border: 'none',
        borderRadius: 11,
        cursor: disabled ? 'not-allowed' : 'pointer',
        transition: 'background 0.3s',
      }}
    >
      <span
        style={{
          position: 'absolute',
          top: 2,
          left: checked ? 22 : 2,
          width: 18,
          height: 18,
          background: '#fff',
          borderRadius: '50%',
          transition: 'left 0.3s',
        }}
      />
      <span
        style={{
          position: 'absolute',
          top: 0,
          left: 6,
          fontSize: 12,
          color: checked ? '#fff' : '#fff',
        }}
      >
        {checked ? checkedChildren : unCheckedChildren}
      </span>
    </button>
  );
};

export default CookieBanner;
