import React, { useState } from 'react';
import {
  Card,
  Table,
  Button,
  Space,
  message,
  Tag,
  Modal,
  Input,
  Select,
  Row,
  Col,
  Statistic,
  Typography,
  Tooltip,
} from 'antd';
import {
  ShareAltOutlined,
  FacebookOutlined,
  TwitterOutlined,
  LinkedinOutlined,
  WhatsAppOutlined,
  MailOutlined,
  LinkOutlined,
  CopyOutlined,
  QrcodeOutlined,
} from '@ant-design/icons';
import type { ColumnsType } from 'antd/es/table';
import dayjs from 'dayjs';

interface PostShare {
  id: number;
  post_id: number;
  platform: string;
  share_url: string;
  shares_count: number;
  clicks_count: number;
  shared_at: string;
}

const PostSharingPage: React.FC = () => {
  const [shares, setShares] = useState<PostShare[]>([]);
  const [loading, setLoading] = useState(false);
  const [shareModalVisible, setShareModalVisible] = useState(false);
  const [selectedPostId, setSelectedPostId] = useState<number | null>(null);
  const [shareUrl, setShareUrl] = useState('');

  const { Text } = Typography;

  const platforms = [
    { key: 'facebook', name: 'Facebook', icon: <FacebookOutlined style={{ color: '#1877F2' }} /> },
    { key: 'twitter', name: 'Twitter', icon: <TwitterOutlined style={{ color: '#1DA1F2' }} /> },
    { key: 'linkedin', name: 'LinkedIn', icon: <LinkedinOutlined style={{ color: '#0A66C2' }} /> },
    { key: 'whatsapp', name: 'WhatsApp', icon: <WhatsAppOutlined style={{ color: '#25D366' }} /> },
    { key: 'email', name: 'Email', icon: <MailOutlined style={{ color: '#EA4335' }} /> },
  ];

  const handleOpenShareModal = (postId: number) => {
    setSelectedPostId(postId);
    const postUrl = `${window.location.origin}/blog/posts/${postId}`;
    setShareUrl(postUrl);
    setShareModalVisible(true);
  };

  const handleShare = async (platform: string) => {
    const encodedUrl = encodeURIComponent(shareUrl);
    const urls: Record<string, string> = {
      facebook: `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}`,
      twitter: `https://twitter.com/intent/tweet?url=${encodedUrl}`,
      linkedin: `https://www.linkedin.com/sharing/share-offsite/?url=${encodedUrl}`,
      whatsapp: `https://wa.me/?text=${encodedUrl}`,
      email: `mailto:?subject=Check out this post&body=${encodedUrl}`,
    };

    window.open(urls[platform], '_blank', 'width=600,height=400');
  };

  const handleCopyLink = () => {
    navigator.clipboard.writeText(shareUrl);
    message.success('Link copied to clipboard!');
  };

  const handleGenerateQrCode = () => {
    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(shareUrl)}`;
    window.open(qrUrl, '_blank');
  };

  const columns: ColumnsType<PostShare> = [
    {
      title: 'Platform',
      dataIndex: 'platform',
      key: 'platform',
      render: (platform: string) => {
        const platformInfo = platforms.find(p => p.key === platform);
        return (
          <Space>
            {platformInfo?.icon}
            <span>{platformInfo?.name || platform}</span>
          </Space>
        );
      },
    },
    {
      title: 'Shares',
      dataIndex: 'shares_count',
      key: 'shares_count',
      render: (count: number) => <Tag color="blue">{count}</Tag>,
      sorter: (a, b) => a.shares_count - b.shares_count,
    },
    {
      title: 'Clicks',
      dataIndex: 'clicks_count',
      key: 'clicks_count',
      render: (count: number) => <Tag color="green">{count}</Tag>,
      sorter: (a, b) => a.clicks_count - b.clicks_count,
    },
    {
      title: 'Shared At',
      dataIndex: 'shared_at',
      key: 'shared_at',
      render: (date: string) => dayjs(date).format('YYYY-MM-DD HH:mm'),
      sorter: (a, b) => dayjs(a.shared_at).unix() - dayjs(b.shared_at).unix(),
    },
  ];

  return (
    <div>
      <Card
        title={
          <Space>
            <ShareAltOutlined />
            <span>Post Sharing & Analytics</span>
          </Space>
        }
      />

      {/* Share Modal */}
      <Modal
        title="Share Post"
        open={shareModalVisible}
        onCancel={() => setShareModalVisible(false)}
        footer={null}
        width={600}
      >
        <Space direction="vertical" style={{ width: '100%' }} size="large">
          <div>
            <Input
              value={shareUrl}
              prefix={<LinkOutlined />}
              suffix={
                <Tooltip title="Copy link">
                  <Button
                    type="text"
                    icon={<CopyOutlined />}
                    onClick={handleCopyLink}
                  />
                </Tooltip>
              }
              readOnly
            />
          </div>

          <div>
            <Text strong>Share on:</Text>
            <Row gutter={[16, 16]} style={{ marginTop: 16 }}>
              {platforms.map((platform) => (
                <Col key={platform.key} span={12}>
                  <Button
                    block
                    size="large"
                    icon={platform.icon}
                    onClick={() => handleShare(platform.key)}
                  >
                    {platform.name}
                  </Button>
                </Col>
              ))}
            </Row>
          </div>

          <div style={{ textAlign: 'center' }}>
            <Button
              icon={<QrcodeOutlined />}
              onClick={handleGenerateQrCode}
            >
              Generate QR Code
            </Button>
          </div>
        </Space>
      </Modal>

      <Card
        style={{ marginTop: 16 }}
        title="Share Statistics"
      >
        <Row gutter={16}>
          <Col span={8}>
            <Statistic
              title="Total Shares"
              value={shares.reduce((sum, s) => sum + s.shares_count, 0)}
              prefix={<ShareAltOutlined />}
            />
          </Col>
          <Col span={8}>
            <Statistic
              title="Total Clicks"
              value={shares.reduce((sum, s) => sum + s.clicks_count, 0)}
              valueStyle={{ color: '#3f8600' }}
            />
          </Col>
          <Col span={8}>
            <Statistic
              title="Platforms"
              value={new Set(shares.map(s => s.platform)).size}
            />
          </Col>
        </Row>
      </Card>

      <Card style={{ marginTop: 16 }} title="Share History">
        <Table
          columns={columns}
          dataSource={shares}
          rowKey="id"
          loading={loading}
          pagination={{ pageSize: 20 }}
        />
      </Card>
    </div>
  );
};

export default PostSharingPage;
