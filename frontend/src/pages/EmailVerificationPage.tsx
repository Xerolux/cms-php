import React, { useEffect, useState } from 'react';
import { useSearchParams, useNavigate, Link } from 'react-router-dom';
import { Result, Button, Spin, message } from 'antd';
import { CheckCircleOutlined, CloseCircleOutlined } from '@ant-design/icons';
import api from '../services/api';

const EmailVerificationPage: React.FC = () => {
  const [searchParams] = useSearchParams();
  const navigate = useNavigate();
  const [verifying, setVerifying] = useState(true);
  const [status, setStatus] = useState<'success' | 'error'>('success');
  const [messageText, setMessageText] = useState('Verifying your email...');

  const token = searchParams.get('token');
  const email = searchParams.get('email');

  useEffect(() => {
    if (!token || !email) {
      setStatus('error');
      setMessageText('Invalid verification link. Missing token or email.');
      setVerifying(false);
      return;
    }

    const verifyEmail = async () => {
      try {
        await api.post('/auth/email/verify', { token, email });
        setStatus('success');
        setMessageText('Your email has been successfully verified! You can now access all features.');
        message.success('Email verified successfully');
      } catch (error: any) {
        setStatus('error');
        setMessageText(error.response?.data?.message || 'Failed to verify email. The link may be invalid or expired.');
        message.error('Verification failed');
      } finally {
        setVerifying(false);
      }
    };

    verifyEmail();
  }, [token, email]);

  return (
    <div style={{ padding: 24, background: '#fff', minHeight: '100vh', display: 'flex', flexDirection: 'column' }}>
       {/* Use a simple container since MainLayout doesn't accept children and uses Outlet */}
      <div style={{
        display: 'flex',
        justifyContent: 'center',
        alignItems: 'center',
        flex: 1
      }}>
        {verifying ? (
          <Spin size="large" tip="Verifying email..." />
        ) : (
          <Result
            status={status}
            icon={status === 'success' ? <CheckCircleOutlined /> : <CloseCircleOutlined />}
            title={status === 'success' ? 'Email Verified!' : 'Verification Failed'}
            subTitle={messageText}
            extra={[
              <Button type="primary" key="console" onClick={() => navigate('/dashboard')}>
                Go to Dashboard
              </Button>,
              status === 'error' && (
                <Button key="resend" onClick={() => navigate('/profile')}>
                  Resend Verification
                </Button>
              ),
            ]}
          />
        )}
      </div>
    </div>
  );
};

export default EmailVerificationPage;
