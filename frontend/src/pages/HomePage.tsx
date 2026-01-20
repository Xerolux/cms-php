import React from 'react';
import { Card, Row, Col, Typography, Button, Space } from 'antd';
import { ReadOutlined, UserOutlined } from '@ant-design/icons';
import { useNavigate } from 'react-router-dom';

const { Title, Paragraph, Text } = Typography;

const HomePage: React.FC = () => {
  const navigate = useNavigate();

  return (
    <div style={{
      minHeight: '100vh',
      background: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
      display: 'flex',
      alignItems: 'center',
      justifyContent: 'center',
      padding: '20px'
    }}>
      <div style={{ maxWidth: '1200px', width: '100%' }}>
        <Row gutter={[24, 24]}>
          {/* Hero Section */}
          <Col span={24}>
            <Card
              style={{
                textAlign: 'center',
                borderRadius: '12px',
                boxShadow: '0 8px 32px rgba(0,0,0,0.1)'
              }}
            >
              <ReadOutlined style={{ fontSize: '72px', color: '#667eea', marginBottom: '24px' }} />
              <Title level={1} style={{ fontSize: '48px', marginBottom: '16px' }}>
                Willkommen auf unserem Blog
              </Title>
              <Paragraph style={{ fontSize: '18px', color: '#666', marginBottom: '32px' }}>
                Entdecke interessante Artikel, Neuigkeiten und Stories
              </Paragraph>
              <Space size="middle">
                <Button
                  type="primary"
                  size="large"
                  style={{ borderRadius: '8px', height: '48px', fontSize: '16px', padding: '0 32px' }}
                >
                  Artikel lesen
                </Button>
                <Button
                  size="large"
                  icon={<UserOutlined />}
                  onClick={() => navigate('/login')}
                  style={{ borderRadius: '8px', height: '48px', fontSize: '16px', padding: '0 32px' }}
                >
                  Admin Login
                </Button>
              </Space>
            </Card>
          </Col>

          {/* Info Cards */}
          <Col xs={24} sm={12} md={8}>
            <Card
              hoverable
              style={{
                height: '100%',
                borderRadius: '12px',
                boxShadow: '0 4px 16px rgba(0,0,0,0.08)',
                transition: 'all 0.3s'
              }}
              styles={{ body: { padding: '32px' } }}
            >
              <Title level={3} style={{ marginBottom: '16px', color: '#667eea' }}>
                📝 Aktuelle Beiträge
              </Title>
              <Paragraph style={{ color: '#666', fontSize: '16px' }}>
                Lies unsere neuesten Artikel und bleibe auf dem Laufenden
              </Paragraph>
            </Card>
          </Col>

          <Col xs={24} sm={12} md={8}>
            <Card
              hoverable
              style={{
                height: '100%',
                borderRadius: '12px',
                boxShadow: '0 4px 16px rgba(0,0,0,0.08)',
                transition: 'all 0.3s'
              }}
              styles={{ body: { padding: '32px' } }}
            >
              <Title level={3} style={{ marginBottom: '16px', color: '#764ba2' }}>
                💬 Diskussionen
              </Title>
              <Paragraph style={{ color: '#666', fontSize: '16px' }}>
                Tausch dich mit unserer Community aus und kommentiere Beiträge
              </Paragraph>
            </Card>
          </Col>

          <Col xs={24} sm={12} md={8}>
            <Card
              hoverable
              style={{
                height: '100%',
                borderRadius: '12px',
                boxShadow: '0 4px 16px rgba(0,0,0,0.08)',
                transition: 'all 0.3s'
              }}
              styles={{ body: { padding: '32px' } }}
            >
              <Title level={3} style={{ marginBottom: '16px', color: '#f093fb' }}>
                🎓 Wissen
              </Title>
              <Paragraph style={{ color: '#666', fontSize: '16px' }}>
                Finde Tutorials, Guides und hilfreiche Ressourcen
              </Paragraph>
            </Card>
          </Col>
        </Row>

        {/* Footer */}
        <div style={{ textAlign: 'center', marginTop: '48px', color: 'white' }}>
          <Text style={{ color: 'rgba(255,255,255,0.8)', fontSize: '14px' }}>
            © 2026 Blog CMS. Alle Rechte vorbehalten.
          </Text>
        </div>
      </div>
    </div>
  );
};

export default HomePage;
